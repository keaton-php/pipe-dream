<?php

namespace R1kon\PipeDream\Tests\Feature;

use R1kon\PipeDream\PipeEnvelope;
use R1kon\PipeDream\PipeHub;
use R1kon\PipeDream\PipeManager;
use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;
use R1kon\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\ErrorsAreFatalRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\ExplodingRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\FatalRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\FirstRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\SecondRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\ThirdRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\UnhandledExplodingRule;
use R1kon\PipeDream\Tests\TestCase;
use stdClass;

class StopSemanticsTest extends TestCase
{
    private function flow(PipeHub $middle): PipeEnvelope
    {
        return PipeManager::create([
            PipeHub::create()->name('Before')->addPipe(FirstRule::class),
            $middle->name('Middle')->then(fn () => Journal::record('middle then')),
            PipeHub::create()->name('After')->addPipe(ThirdRule::class),
        ])
            ->subject(new stdClass())
            ->then(fn () => Journal::record('manager then'))
            ->sendIt();
    }

    public function test_by_default_a_fatal_ends_only_the_current_hub(): void
    {
        $envelope = $this->flow(PipeHub::create()->addPipes([FatalRule::class, SecondRule::class]));

        // SecondRule and the hub's then() never run; the next hub and the manager's then() do.
        $this->assertSame(['FirstRule', 'FatalRule', 'ThirdRule', 'manager then'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertSame(PipeRule::FATAL_FAIL, $envelope->get(FatalRule::class));

        $stop = $envelope->logger()->warnings()->firstWhere('key', 'PipeHub:sendIt');
        $this->assertSame('hub', $stop['context']['ends']);
        $this->assertSame('Middle', $stop['context']['name']);
    }

    public function test_with_stop_on_fatal_a_fatal_ends_the_whole_flow(): void
    {
        $envelope = $this->flow(PipeHub::create()->addPipes([FatalRule::class, SecondRule::class])->stopOnFatal());

        $this->assertSame(['FirstRule', 'FatalRule'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertSame('flow', $envelope->logger()->warnings()->firstWhere('key', 'PipeHub:sendIt')['context']['ends']);
        $this->assertNotNull($envelope->logger()->warnings()->firstWhere('key', 'PipeManager:sendIt'));
    }

    public function test_stop_on_fatal_does_nothing_until_a_fatal_happens(): void
    {
        $this->flow(PipeHub::create()->addPipe(SecondRule::class)->stopOnFatal());

        $this->assertSame(['FirstRule', 'SecondRule', 'middle then', 'ThirdRule', 'manager then'], Journal::$entries);
    }

    public function test_a_standalone_hub_returns_the_envelope_after_a_fatal(): void
    {
        $envelope = PipeHub::create()->addPipes([FatalRule::class, SecondRule::class])->stopOnFatal()->sendIt(new stdClass());

        $this->assertSame(['FatalRule'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
    }

    public function test_by_default_an_error_is_logged_and_the_hub_carries_on(): void
    {
        $envelope = $this->flow(PipeHub::create()->addPipes([ErrorRule::class, SecondRule::class]));

        $this->assertSame(['FirstRule', 'ErrorRule', 'SecondRule', 'middle then', 'ThirdRule', 'manager then'], Journal::$entries);
        $this->assertTrue($envelope->hasErrors());
        $this->assertFalse($envelope->hasFatalErrors());
        $this->assertSame(PipeRule::FAIL, $envelope->get(ErrorRule::class));
        $this->assertCount(1, $envelope->results()->failures());
    }

    public function test_an_unexpected_exception_in_a_rule_is_logged_as_a_failure_and_the_hub_carries_on(): void
    {
        $envelope = $this->flow(PipeHub::create()->addPipes([ExplodingRule::class, SecondRule::class]));

        $this->assertSame(['FirstRule', 'ExplodingRule', 'SecondRule', 'middle then', 'ThirdRule', 'manager then'], Journal::$entries);
        $this->assertSame(PipeRule::FAIL, $envelope->get(ExplodingRule::class));
        $this->assertStringContainsString('Boom', $envelope->logger()->errors()->first()['data']);
    }

    public function test_with_stop_on_error_an_error_ends_the_hub_and_on_failure_runs(): void
    {
        $envelope = $this->flow(
            PipeHub::create()
                ->addPipes([ErrorRule::class, SecondRule::class])
                ->stopOnError()
                ->onFailure(fn ($subject, $exception) => Journal::record('onFailure: '.$exception->getMessage()))
        );

        $this->assertSame(['FirstRule', 'ErrorRule', 'onFailure: Something is off', 'ThirdRule', 'manager then'], Journal::$entries);
        $this->assertFalse($envelope->hasFatalErrors());
    }

    public function test_on_failure_runs_when_a_rule_lets_its_exception_escape(): void
    {
        $this->flow(
            PipeHub::create()
                ->addPipes([UnhandledExplodingRule::class, SecondRule::class])
                ->onFailure(fn ($envelope) => Journal::record('onFailure'))
        );

        $this->assertSame(['FirstRule', 'UnhandledExplodingRule', 'onFailure', 'ThirdRule', 'manager then'], Journal::$entries);
    }

    public function test_failure_is_fatal_ends_the_whole_flow(): void
    {
        $envelope = $this->flow(
            PipeHub::create()
                ->addPipes([ErrorRule::class, SecondRule::class])
                ->stopOnError()
                ->failureIsFatal()
                ->onFailure(fn () => Journal::record('onFailure'))
        );

        $this->assertSame(['FirstRule', 'ErrorRule', 'onFailure'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertStringStartsWith('Failure is fatal', $envelope->logger()->fatals()->last()['data']);
    }

    public function test_a_rule_with_errors_are_fatal_turns_its_error_into_a_fatal(): void
    {
        $envelope = $this->flow(PipeHub::create()->addPipes([ErrorsAreFatalRule::class, SecondRule::class]));

        $this->assertSame(['FirstRule', 'ErrorsAreFatalRule', 'ThirdRule', 'manager then'], Journal::$entries);
        $this->assertSame(PipeRule::FATAL_FAIL, $envelope->get(ErrorsAreFatalRule::class));
        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertFalse($envelope->hasErrors());
    }
}
