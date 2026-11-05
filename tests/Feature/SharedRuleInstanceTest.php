<?php

namespace Keaton\PipeDream\Tests\Feature;

use Illuminate\Container\Container;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\MagicResultRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ReentrantRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SecondRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ThirdRule;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

/**
 * A rule bound as a singleton is the same object every time the container
 * resolves it. Hubs run a fresh copy per run, so the shared instance is
 * never executed or changed, and one hub's skip or stop policy never sticks.
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

    public function test_stop_on_error_does_not_follow_a_shared_rule_into_the_next_hub(): void
    {
        $this->app->singleton(ErrorRule::class);

        PipeManager::create([
            PipeHub::create()->addPipes([ErrorRule::class, SecondRule::class])->stopOnError(),
            PipeHub::create()->addPipes([ErrorRule::class, ThirdRule::class]),
        ])->subject(new stdClass())->sendIt();

        // The first hub stops at the error; the second carries on past it.
        $this->assertSame(['ErrorRule', 'ErrorRule', 'ThirdRule'], Journal::$entries);
    }

    public function test_stop_on_error_does_not_follow_a_shared_rule_into_the_next_run(): void
    {
        $this->app->singleton(ErrorRule::class);

        PipeHub::create()->addPipes([ErrorRule::class, SecondRule::class])->stopOnError()->sendIt(new stdClass());
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
            PipeHub::create()->addPipes([ErrorRule::class, SecondRule::class])->stopOnError(),
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
            'outer stops on error: no',
            'outer container is the inner one: no',
        ], Journal::$entries);
        $this->assertSame(PipeRule::SUCCESS, $envelope->get(ReentrantRule::class));
        $this->assertSame('after the nested run', $envelope->logger()->infos()->firstWhere('key', ReentrantRule::class)['data']);
    }

    public function test_a_rule_that_sets_errors_are_fatal_keeps_it(): void
    {
        $envelope = PipeHub::create()
            ->addPipes([\Keaton\PipeDream\Tests\Fixtures\Rules\ErrorsAreFatalRule::class, SecondRule::class])
            ->stopOnError()
            ->sendIt(new stdClass());

        $this->assertSame(PipeRule::FATAL_FAIL, $envelope->get(\Keaton\PipeDream\Tests\Fixtures\Rules\ErrorsAreFatalRule::class));
    }
}
