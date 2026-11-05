<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SecondRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ThirdRule;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

/**
 * A rule bound as a singleton is the same object in every hub and every run.
 * One hub's skip or stop policy must never stick to it.
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

    public function test_a_rule_that_sets_errors_are_fatal_keeps_it(): void
    {
        $envelope = PipeHub::create()
            ->addPipes([\Keaton\PipeDream\Tests\Fixtures\Rules\ErrorsAreFatalRule::class, SecondRule::class])
            ->stopOnError()
            ->sendIt(new stdClass());

        $this->assertSame(PipeRule::FATAL_FAIL, $envelope->get(\Keaton\PipeDream\Tests\Fixtures\Rules\ErrorsAreFatalRule::class));
    }
}
