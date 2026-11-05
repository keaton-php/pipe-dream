<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\ArgumentCountRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SecondRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ThirdRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\TypeErrorRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\UnhandledTypeErrorRule;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

/**
 * PHP errors (TypeError, ArgumentCountError, ...) are failures like any
 * exception: recorded, handled by onFailure() and failureIsFatal().
 */
class ThrowableTest extends TestCase
{
    private function flow(PipeHub $middle): PipeEnvelope
    {
        return PipeManager::create([
            PipeHub::create()->addPipe(FirstRule::class),
            $middle->then(fn () => Journal::record('middle then')),
            PipeHub::create()->addPipe(ThirdRule::class),
        ])
            ->subject(new stdClass())
            ->then(fn () => Journal::record('manager then'))
            ->sendIt();
    }

    public function test_a_type_error_in_a_rule_is_recorded_as_a_failure(): void
    {
        $envelope = $this->flow(PipeHub::create()->addPipes([TypeErrorRule::class, SecondRule::class]));

        $this->assertSame(['FirstRule', 'TypeErrorRule', 'SecondRule', 'middle then', 'ThirdRule', 'manager then'], Journal::$entries);
        $this->assertSame(PipeRule::FAIL, $envelope->get(TypeErrorRule::class));
        $this->assertSame(\TypeError::class, $envelope->logger()->errors()->first()['context']['exception']);
    }

    public function test_an_argument_count_error_in_a_rule_is_recorded_as_a_failure(): void
    {
        $envelope = $this->flow(PipeHub::create()->addPipes([ArgumentCountRule::class, SecondRule::class]));

        $this->assertSame(['FirstRule', 'ArgumentCountRule', 'SecondRule', 'middle then', 'ThirdRule', 'manager then'], Journal::$entries);
        $this->assertSame(PipeRule::FAIL, $envelope->get(ArgumentCountRule::class));
        $this->assertSame(\ArgumentCountError::class, $envelope->logger()->errors()->first()['context']['exception']);
    }

    public function test_an_argument_count_error_in_a_closure_pipe_is_recorded_as_a_failure(): void
    {
        $envelope = PipeHub::create()
            ->addPipes([function ($subject) {
                $twoArguments = fn ($first, $second) => $first.$second;

                return $twoArguments('only one');
            }, FirstRule::class])
            ->sendIt(new stdClass());

        $this->assertSame(['FirstRule'], Journal::$entries);
        $this->assertCount(1, $envelope->results()->failures());
        $this->assertSame(\ArgumentCountError::class, $envelope->logger()->errors()->first()['context']['exception']);
    }

    public function test_a_type_error_that_escapes_a_rule_runs_on_failure(): void
    {
        $envelope = $this->flow(
            PipeHub::create()
                ->addPipes([UnhandledTypeErrorRule::class, SecondRule::class])
                ->onFailure(fn ($subject, $exception) => Journal::record('onFailure: '.get_class($exception)))
        );

        $this->assertSame(['FirstRule', 'UnhandledTypeErrorRule', 'onFailure: TypeError', 'ThirdRule', 'manager then'], Journal::$entries);
        $this->assertSame(PipeRule::FAIL, $envelope->get(UnhandledTypeErrorRule::class));
    }

    public function test_failure_is_fatal_applies_to_a_type_error(): void
    {
        $envelope = $this->flow(
            PipeHub::create()
                ->addPipes([UnhandledTypeErrorRule::class, SecondRule::class])
                ->failureIsFatal()
                ->onFailure(fn () => Journal::record('onFailure'))
        );

        $this->assertSame(['FirstRule', 'UnhandledTypeErrorRule', 'onFailure'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
    }

    public function test_a_type_error_in_skip_if_is_a_hub_failure(): void
    {
        $envelope = $this->flow(
            PipeHub::create()
                ->addPipe(SecondRule::class)
                ->skipIf(fn ($subject) => strlen([]))
                ->onFailure(fn () => Journal::record('onFailure'))
        );

        $this->assertSame(['FirstRule', 'onFailure', 'ThirdRule', 'manager then'], Journal::$entries);
    }

    public function test_an_error_thrown_by_a_then_callback_is_logged(): void
    {
        $envelope = PipeHub::create()
            ->addPipe(FirstRule::class)
            ->then(fn ($subject) => strlen([]))
            ->sendIt(new stdClass());

        $this->assertSame(\TypeError::class, $envelope->logger()->errors()->first()['context']['exception']);
    }

    public function test_an_error_escaping_a_hub_is_caught_by_the_manager(): void
    {
        $envelope = $this->flow(
            PipeHub::create()
                ->addPipe(UnhandledTypeErrorRule::class)
                ->onFailure(fn ($subject) => strlen([]))   // onFailure itself throws a TypeError
        );

        // The flow stops and the manager's then() does not run, but nothing escapes.
        $this->assertSame(['FirstRule', 'UnhandledTypeErrorRule'], Journal::$entries);
        $this->assertSame(
            \TypeError::class,
            $envelope->logger()->errors()->firstWhere('key', 'PipeManager:sendIt')['context']['exception']
        );
    }
}
