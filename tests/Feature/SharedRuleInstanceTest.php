<?php

namespace Keaton\PipeDream\Tests\Feature;

use Illuminate\Container\Container;
use Keaton\PipeDream\Exceptions\PipeHubException;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\CountingPersist;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\CountingCloneRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\PrivateCloneRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\RefusingCloneRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\MagicResultRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ReentrantRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SecondRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ThirdRule;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

/**
 * A rule bound as shared (singleton or instance) is the same object every
 * time the container resolves it. Hubs run a copy of a shared rule per run,
 * so the shared instance is never executed or changed and one hub's skip or
 * errorsAreFatal() policy never sticks. A transient resolution runs as it is.
 */
class SharedRuleInstanceTest extends TestCase
{
    public function test_a_skip_if_does_not_follow_a_shared_rule_into_the_next_hub(): void
    {
        $this->app->singleton(FirstRule::class);

        $envelope = PipeManager::create([
            PipeHub::create()->addPipe(FirstRule::class)->skipIf(fn () => true),
            PipeHub::create()->addPipe(FirstRule::class),
        ])->subject(new stdClass())->sendIt();

        $this->assertSame(['FirstRule'], Journal::$entries);
        $this->assertSame([PipeRule::SKIP, PipeRule::SUCCESS], $envelope->results()->map->result()->values()->all());
    }

    public function test_a_skip_if_does_not_follow_a_shared_rule_into_the_next_run(): void
    {
        $this->app->singleton(FirstRule::class);
        $skip = true;
        $hub = PipeHub::create()->addPipe(FirstRule::class)->skipIf(function () use (&$skip) {
            return $skip;
        });

        $hub->sendIt(new stdClass());
        $skip = false;
        $second = $hub->sendIt(new stdClass());

        $this->assertSame(['FirstRule'], Journal::$entries);
        $this->assertSame(PipeRule::SUCCESS, $second->get(FirstRule::class));
    }

    public function test_errors_are_fatal_does_not_follow_a_shared_rule_into_the_next_hub(): void
    {
        $this->app->singleton(ErrorRule::class);

        PipeManager::create([
            PipeHub::create()->addPipes([ErrorRule::class, SecondRule::class])->errorsAreFatal()->fatalsStopThisHubOnly(),
            PipeHub::create()->addPipes([ErrorRule::class, ThirdRule::class]),
        ])->subject(new stdClass())->sendIt();

        // The first hub stops at the error; the second carries on past it.
        $this->assertSame(['ErrorRule', 'ErrorRule', 'ThirdRule'], Journal::$entries);
    }

    public function test_errors_are_fatal_does_not_follow_a_shared_rule_into_the_next_run(): void
    {
        $this->app->singleton(ErrorRule::class);

        PipeHub::create()->addPipes([ErrorRule::class, SecondRule::class])->errorsAreFatal()->sendIt(new stdClass());
        PipeHub::create()->addPipes([ErrorRule::class, ThirdRule::class])->sendIt(new stdClass());

        $this->assertSame(['ErrorRule', 'ErrorRule', 'ThirdRule'], Journal::$entries);
    }

    public function test_the_shared_instance_itself_is_never_run_or_changed(): void
    {
        $this->app->singleton(ErrorRule::class);
        $shared = $this->app->make(ErrorRule::class);
        $pristine = serialize($shared);

        PipeManager::create([
            PipeHub::create()->addPipe(ErrorRule::class)->skipIf(fn () => true),
            PipeHub::create()->addPipes([ErrorRule::class, SecondRule::class])->errorsAreFatal()->onError(fn () => null),
        ])->subject(new stdClass())->sendIt();

        $this->assertSame($pristine, serialize($this->app->make(ErrorRule::class)));
        $this->assertFalse($shared->willSkip(new PipeEnvelope(new stdClass())));
    }

    public function test_a_shared_rule_cannot_touch_a_finished_envelope(): void
    {
        $this->app->singleton(MagicResultRule::class);

        $envelope = PipeHub::create()->addPipe(MagicResultRule::class)->sendIt(new stdClass());

        $this->expectException(\BadMethodCallException::class);

        try {
            $this->app->make(MagicResultRule::class)->setBotScoreResult(0.99);
        } finally {
            $this->assertSame(0.25, $envelope->get('bot_score'));
        }
    }

    public function test_a_finished_rule_lets_go_of_the_envelope(): void
    {
        $hub = PipeHub::create()->addPipe(MagicResultRule::class);
        $envelope = $hub->sendIt(new stdClass());

        $this->expectException(\BadMethodCallException::class);

        try {
            $hub->getInstantiatedPipes()[0]->setBotScoreResult(0.99);
        } finally {
            $this->assertSame(0.25, $envelope->get('bot_score'));
        }
    }

    public function test_a_nested_run_of_a_shared_rule_shares_nothing_with_the_outer_run(): void
    {
        $this->app->singleton(ReentrantRule::class);
        ReentrantRule::$innerContainer = $inner = new Container();
        $inner->instance(ReentrantRule::class, $this->app->make(ReentrantRule::class));

        $envelope = PipeHub::create()->addPipe(ReentrantRule::class)->sendIt(new stdClass());
        ReentrantRule::$innerContainer = null;

        // The inner run skipped its copy (skipIf), so 'inner ran' never appears.
        $this->assertSame([
            'outer subject intact: yes',
            'outer skipping: no',
            'outer errors are fatal: no',
            'outer container is the inner one: no',
        ], Journal::$entries);
        $this->assertSame(PipeRule::SUCCESS, $envelope->get(ReentrantRule::class));
        $this->assertSame('after the nested run', $envelope->logger()->infos()->firstWhere('key', ReentrantRule::class)['data']);
    }

    public function test_a_transient_rule_runs_as_the_object_the_container_returned(): void
    {
        $rule = new FirstRule();
        $this->app->bind(FirstRule::class, fn () => $rule);

        $hub = PipeHub::create()->addPipe(FirstRule::class);
        $hub->sendIt(new stdClass());

        $this->assertSame($rule, $hub->getInstantiatedPipes()[0]);
        $this->assertSame(['FirstRule'], Journal::$entries);
    }

    public function test_a_singleton_rule_runs_as_a_copy(): void
    {
        $this->app->singleton(FirstRule::class);

        $hub = PipeHub::create()->addPipe(FirstRule::class);
        $hub->sendIt(new stdClass());

        $this->assertInstanceOf(FirstRule::class, $hub->getInstantiatedPipes()[0]);
        $this->assertNotSame($this->app->make(FirstRule::class), $hub->getInstantiatedPipes()[0]);
    }

    public function test_an_instance_bound_rule_runs_as_a_copy(): void
    {
        $this->app->instance(FirstRule::class, $shared = new FirstRule());

        $hub = PipeHub::create()->addPipe(FirstRule::class);
        $hub->sendIt(new stdClass());

        $this->assertNotSame($shared, $hub->getInstantiatedPipes()[0]);
    }

    public function test_a_shared_rule_that_cannot_be_copied_is_a_clear_configuration_error(): void
    {
        $this->app->singleton(PrivateCloneRule::class);
        $persist = new CountingPersist();

        try {
            PipeManager::create([
                PipeHub::create()->addPipes([FirstRule::class, PrivateCloneRule::class])->onError(fn () => Journal::record('onError'))->onFatal(fn () => Journal::record('onFatal')),
                PipeHub::create()->addPipe(SecondRule::class),
            ])->subject(PipeEnvelope::wrap(new stdClass())->persistWith($persist))->sendIt();

            $this->fail('A non-cloneable shared rule must not be swallowed.');
        } catch (PipeHubException $e) {
            $this->assertStringContainsString(PrivateCloneRule::class, $e->getMessage());
            $this->assertStringContainsString('Bind it non-shared', $e->getMessage());
        }

        // Nothing ran as if the rule had merely failed, and the log was still written.
        $this->assertSame([], Journal::$entries);
        $this->assertSame(1, $persist->calls);
    }

    public function test_a_shared_rule_whose_clone_refuses_is_a_clear_configuration_error(): void
    {
        $this->app->singleton(RefusingCloneRule::class);

        try {
            PipeHub::create()->addPipe(RefusingCloneRule::class)->sendIt(new stdClass());
            $this->fail('A refused copy must not be swallowed.');
        } catch (PipeHubException $e) {
            $this->assertStringContainsString(RefusingCloneRule::class, $e->getMessage());
            $this->assertInstanceOf(\LogicException::class, $e->getPrevious());
        }
    }

    public function test_a_transient_rule_that_cannot_be_copied_runs_normally(): void
    {
        PipeHub::create()->addPipe(PrivateCloneRule::class)->sendIt(new stdClass());

        $this->assertSame(['PrivateCloneRule'], Journal::$entries);
    }

    public function test_a_public_clone_method_is_respected(): void
    {
        $this->app->singleton(CountingCloneRule::class);
        CountingCloneRule::$copies = 0;

        $hub = PipeHub::create()->addPipe(CountingCloneRule::class);
        $hub->sendIt(new stdClass());
        $hub->sendIt(new stdClass());

        $this->assertSame(['copy 1', 'copy 2'], Journal::$entries);
        $this->assertNull($this->app->make(CountingCloneRule::class)->copyNumber);
    }

    public function test_a_rule_that_sets_errors_are_fatal_keeps_it(): void
    {
        $envelope = PipeHub::create()
            ->addPipes([\Keaton\PipeDream\Tests\Fixtures\Rules\ErrorsAreFatalRule::class, SecondRule::class])
            ->sendIt(new stdClass());

        $this->assertSame(PipeRule::FATAL_FAIL, $envelope->get(\Keaton\PipeDream\Tests\Fixtures\Rules\ErrorsAreFatalRule::class));
    }
}
