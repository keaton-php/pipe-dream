<?php

namespace Keaton\PipeDream\Tests\Feature;

use Closure;
use Keaton\PipeDream\Exceptions\PipeRuleError;
use Keaton\PipeDream\Exceptions\PipeRuleFatal;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorsAreFatalRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ExplodingRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FatalRule;
use Keaton\PipeDream\Tests\TestCase;
use RuntimeException;
use stdClass;

/**
 * How far a stop reaches. A rule says what happened (error() or fatal());
 * the hub says how far it reaches (fatalsStopThisHubOnly(), errorsAreFatal()).
 */
class StopSemanticsTest extends TestCase
{
    /**
     * A hub named $name with $count pipes that each record "{$name}.p{n}",
     * except where $at puts a rule class (which records its own name).
     */
    private function hub(string $name, int $count, array $at = []): PipeHub
    {
        $pipes = [];

        for ($n = 1; $n <= $count; $n++) {
            $pipes[] = $at[$n] ?? fn () => Journal::record("{$name}.p{$n}");
        }

        return PipeHub::create()
            ->name($name)
            ->addPipes($pipes)
            ->then(fn () => Journal::record("{$name} then"));
    }

    /** Five hubs of two pipes; $hubs replaces hubs by position (1-based). */
    private function manager(array $hubs = []): PipeManager
    {
        $all = [];

        for ($h = 1; $h <= 5; $h++) {
            $all[] = $hubs[$h] ?? $this->hub("h{$h}", 2);
        }

        return PipeManager::create($all)
            ->subject(new stdClass())
            ->then(fn () => Journal::record('manager then'));
    }

    // ------------------------------------------------------------------
    // A hub run on its own

    public function test_a_fatal_on_pipe_3_of_10_stops_the_hub_and_skips_then(): void
    {
        $envelope = $this->hub('hub', 10, [3 => FatalRule::class])->sendIt(new stdClass());

        $this->assertSame(['hub.p1', 'hub.p2', 'FatalRule'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertSame(PipeRule::FATAL_FAIL, $envelope->get(FatalRule::class));
        $this->assertSame('flow', $envelope->logger()->warnings()->firstWhere('key', 'PipeHub:sendIt')['context']['ends']);
    }

    public function test_an_error_on_pipe_3_of_10_lets_pipe_4_and_the_rest_run(): void
    {
        $envelope = $this->hub('hub', 10, [3 => ErrorRule::class])->sendIt(new stdClass());

        $this->assertSame(['hub.p1', 'hub.p2', 'ErrorRule', 'hub.p4', 'hub.p5', 'hub.p6', 'hub.p7', 'hub.p8', 'hub.p9', 'hub.p10', 'hub then'], Journal::$entries);
        $this->assertTrue($envelope->hasErrors());
        $this->assertFalse($envelope->hasFatalErrors());
        $this->assertSame(PipeRule::FAIL, $envelope->get(ErrorRule::class));
    }

    public function test_an_unexpected_exception_is_handled_like_an_error(): void
    {
        $envelope = $this->hub('hub', 4, [2 => ExplodingRule::class])->sendIt(new stdClass());

        $this->assertSame(['hub.p1', 'ExplodingRule', 'hub.p3', 'hub.p4', 'hub then'], Journal::$entries);
        $this->assertSame(PipeRule::FAIL, $envelope->get(ExplodingRule::class));
        $this->assertStringContainsString('Boom', $envelope->logger()->errors()->first()['data']);
        $this->assertFalse($envelope->hasFatalErrors());
    }

    public function test_fatals_stop_this_hub_only_changes_nothing_for_a_hub_run_on_its_own(): void
    {
        $envelope = $this->hub('hub', 4, [2 => FatalRule::class])->fatalsStopThisHubOnly()->sendIt(new stdClass());

        $this->assertSame(['hub.p1', 'FatalRule'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
    }

    public function test_errors_are_fatal_on_a_hub_run_on_its_own_stops_it_at_the_error(): void
    {
        $envelope = $this->hub('hub', 4, [2 => ErrorRule::class])->errorsAreFatal()->sendIt(new stdClass());

        $this->assertSame(['hub.p1', 'ErrorRule'], Journal::$entries);
        $this->assertSame(PipeRule::FATAL_FAIL, $envelope->get(ErrorRule::class));
    }

    // ------------------------------------------------------------------
    // A manager: a fatal stops everything by default

    public function test_a_fatal_in_hub_2_stops_the_whole_flow_by_default(): void
    {
        $envelope = $this->manager([2 => $this->hub('h2', 3, [2 => FatalRule::class])])->sendIt();

        // The rest of hub 2, hubs 3 to 5, hub 2's then() and the manager's then() never run.
        $this->assertSame(['h1.p1', 'h1.p2', 'h1 then', 'h2.p1', 'FatalRule'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());

        $stop = $envelope->logger()->warnings()->firstWhere('key', 'PipeHub:sendIt');
        $this->assertSame('flow', $stop['context']['ends']);
        $this->assertSame('h2', $stop['context']['name']);
        $this->assertSame('Flow stopped by a fatal: Cannot continue', $envelope->logger()->warnings()->firstWhere('key', 'PipeManager:sendIt')['data']);
    }

    public function test_with_fatals_stop_this_hub_only_hub_3_runs_and_the_managers_then_runs(): void
    {
        $envelope = $this->manager([2 => $this->hub('h2', 3, [2 => FatalRule::class])->fatalsStopThisHubOnly()])->sendIt();

        $this->assertSame([
            'h1.p1', 'h1.p2', 'h1 then',
            'h2.p1', 'FatalRule',
            'h3.p1', 'h3.p2', 'h3 then',
            'h4.p1', 'h4.p2', 'h4 then',
            'h5.p1', 'h5.p2', 'h5 then',
            'manager then',
        ], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertSame('hub', $envelope->logger()->warnings()->firstWhere('key', 'PipeHub:sendIt')['context']['ends']);
        $this->assertNull($envelope->logger()->warnings()->firstWhere('key', 'PipeManager:sendIt'));
    }

    public function test_containment_is_per_hub(): void
    {
        // Hub 2 contains its fatal; hub 4 does not, so the flow stops there.
        $this->manager([
            2 => $this->hub('h2', 2, [1 => FatalRule::class])->fatalsStopThisHubOnly(),
            4 => $this->hub('h4', 2, [2 => FatalRule::class]),
        ])->sendIt();

        $this->assertSame(['h1.p1', 'h1.p2', 'h1 then', 'FatalRule', 'h3.p1', 'h3.p2', 'h3 then', 'h4.p1', 'FatalRule'], Journal::$entries);
    }

    public function test_an_error_in_a_manager_carries_on_with_the_next_pipe(): void
    {
        $envelope = $this->manager([2 => $this->hub('h2', 3, [2 => ErrorRule::class])])->sendIt();

        $this->assertSame([
            'h1.p1', 'h1.p2', 'h1 then',
            'h2.p1', 'ErrorRule', 'h2.p3', 'h2 then',
            'h3.p1', 'h3.p2', 'h3 then',
            'h4.p1', 'h4.p2', 'h4 then',
            'h5.p1', 'h5.p2', 'h5 then',
            'manager then',
        ], Journal::$entries);
        $this->assertTrue($envelope->hasErrors());
        $this->assertFalse($envelope->hasFatalErrors());
    }

    // ------------------------------------------------------------------
    // errorsAreFatal()

    public function test_errors_are_fatal_makes_an_error_stop_everything(): void
    {
        $envelope = $this->manager([2 => $this->hub('h2', 3, [2 => ErrorRule::class])->errorsAreFatal()])->sendIt();

        $this->assertSame(['h1.p1', 'h1.p2', 'h1 then', 'h2.p1', 'ErrorRule'], Journal::$entries);
        $this->assertSame(PipeRule::FATAL_FAIL, $envelope->get(ErrorRule::class));
        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertFalse($envelope->hasErrors());
    }

    public function test_errors_are_fatal_makes_an_unexpected_exception_stop_everything(): void
    {
        $envelope = $this->manager([2 => $this->hub('h2', 3, [2 => ExplodingRule::class])->errorsAreFatal()])->sendIt();

        $this->assertSame(['h1.p1', 'h1.p2', 'h1 then', 'h2.p1', 'ExplodingRule'], Journal::$entries);
        $this->assertSame(PipeRule::FATAL_FAIL, $envelope->get(ExplodingRule::class));
        $this->assertStringContainsString('Boom', $envelope->logger()->fatals()->first()['data']);
    }

    public function test_errors_are_fatal_with_fatals_stop_this_hub_only_ends_just_that_hub(): void
    {
        $envelope = $this->manager([
            2 => $this->hub('h2', 3, [2 => ErrorRule::class])->errorsAreFatal()->fatalsStopThisHubOnly(),
        ])->sendIt();

        $this->assertSame([
            'h1.p1', 'h1.p2', 'h1 then',
            'h2.p1', 'ErrorRule',
            'h3.p1', 'h3.p2', 'h3 then',
            'h4.p1', 'h4.p2', 'h4 then',
            'h5.p1', 'h5.p2', 'h5 then',
            'manager then',
        ], Journal::$entries);
        $this->assertSame(PipeRule::FATAL_FAIL, $envelope->get(ErrorRule::class));
    }

    public function test_errors_are_fatal_applies_only_to_its_own_hub(): void
    {
        $this->manager([
            2 => $this->hub('h2', 2, [1 => ErrorRule::class]),
            3 => $this->hub('h3', 2)->errorsAreFatal(),
        ])->sendIt();

        $this->assertSame(['h1.p1', 'h1.p2', 'h1 then', 'ErrorRule', 'h2.p2', 'h2 then', 'h3.p1', 'h3.p2', 'h3 then', 'h4.p1', 'h4.p2', 'h4 then', 'h5.p1', 'h5.p2', 'h5 then', 'manager then'], Journal::$entries);
    }

    public function test_a_rule_with_errors_are_fatal_stops_everything(): void
    {
        $envelope = $this->manager([2 => $this->hub('h2', 2, [1 => ErrorsAreFatalRule::class])])->sendIt();

        $this->assertSame(['h1.p1', 'h1.p2', 'h1 then', 'ErrorsAreFatalRule'], Journal::$entries);
        $this->assertSame(PipeRule::FATAL_FAIL, $envelope->get(ErrorsAreFatalRule::class));
        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertFalse($envelope->hasErrors());
    }

    // ------------------------------------------------------------------
    // Something outside the pipes fails

    public function test_a_failure_outside_the_pipes_stops_as_a_fatal(): void
    {
        $envelope = $this->manager([
            2 => $this->hub('h2', 2)->skipIf(fn () => throw new RuntimeException('skipIf broke')),
        ])->sendIt();

        $this->assertSame(['h1.p1', 'h1.p2', 'h1 then'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertStringContainsString('skipIf broke', $envelope->logger()->fatals()->firstWhere('key', 'PipeHub:sendIt')['data']);
    }

    public function test_a_failure_outside_the_pipes_is_contained_like_a_fatal(): void
    {
        $this->manager([
            2 => $this->hub('h2', 2)->skipIf(fn () => throw new RuntimeException('skipIf broke'))->fatalsStopThisHubOnly(),
        ])->sendIt();

        $this->assertSame(['h1.p1', 'h1.p2', 'h1 then', 'h3.p1', 'h3.p2', 'h3 then', 'h4.p1', 'h4.p2', 'h4 then', 'h5.p1', 'h5.p2', 'h5 then', 'manager then'], Journal::$entries);
    }

    // ------------------------------------------------------------------
    // onError() and onFatal()

    private function hook(string $label): Closure
    {
        return function ($subject, $exception) use ($label) {
            Journal::record(sprintf(
                '%s(%s%s)',
                $label,
                $subject instanceof PipeEnvelope ? '' : 'NOT AN ENVELOPE: ',
                $exception->getMessage()
            ));
        };
    }

    public function test_on_error_runs_on_the_hub_then_the_manager_for_every_error_and_crash(): void
    {
        $this->manager([
            2 => $this->hub('h2', 3, [1 => ErrorRule::class, 3 => ExplodingRule::class])->onError($this->hook('h2 onError')),
            4 => $this->hub('h4', 2, [2 => ErrorRule::class]),
        ])->onError($this->hook('manager onError'))->onFatal($this->hook('manager onFatal'))->sendIt();

        $this->assertSame([
            'h1.p1', 'h1.p2', 'h1 then',
            'ErrorRule', 'h2 onError(Something is off)', 'manager onError(Something is off)',
            'h2.p2',
            'ExplodingRule', 'h2 onError(Boom)', 'manager onError(Boom)',
            'h2 then',
            'h3.p1', 'h3.p2', 'h3 then',
            'h4.p1', 'ErrorRule', 'manager onError(Something is off)', 'h4 then',
            'h5.p1', 'h5.p2', 'h5 then',
            'manager then',
        ], Journal::$entries);
    }

    public function test_on_error_gets_the_error_itself(): void
    {
        $seen = [];

        $this->hub('hub', 2, [1 => ErrorRule::class, 2 => ExplodingRule::class])
            ->onError(function ($subject, $exception) use (&$seen) {
                $seen[] = get_class($exception);
            })
            ->sendIt(new stdClass());

        $this->assertSame([PipeRuleError::class, RuntimeException::class], $seen);
    }

    public function test_hooks_get_the_exception_in_their_second_parameter_whatever_its_name(): void
    {
        $seen = [];

        $this->hub('hub', 2, [1 => ErrorRule::class, 2 => FatalRule::class])
            ->onError(function ($post, $error) use (&$seen) {
                $seen[] = [get_class($post), get_class($error)];
            })
            ->onFatal(function ($p, PipeRuleFatal $fatal) use (&$seen) {
                $seen[] = [get_class($p), get_class($fatal)];
            })
            ->sendIt(new stdClass());

        $this->assertSame([[PipeEnvelope::class, PipeRuleError::class], [PipeEnvelope::class, PipeRuleFatal::class]], $seen);
    }

    public function test_on_fatal_runs_on_the_hub_then_the_manager_when_a_fatal_stops_the_flow(): void
    {
        $envelope = $this->manager([
            3 => $this->hub('h3', 2, [1 => FatalRule::class])->onError($this->hook('h3 onError'))->onFatal($this->hook('h3 onFatal')),
        ])->onError($this->hook('manager onError'))->onFatal($this->hook('manager onFatal'))->sendIt();

        $this->assertSame([
            'h1.p1', 'h1.p2', 'h1 then',
            'h2.p1', 'h2.p2', 'h2 then',
            'FatalRule', 'h3 onFatal(Cannot continue)', 'manager onFatal(Cannot continue)',
        ], Journal::$entries);

        // Logged before the hooks ran, so the log reads in the order things happened.
        $this->assertTrue($envelope->hasFatalErrors());
    }

    public function test_a_contained_fatal_runs_the_hubs_on_fatal_but_not_the_managers(): void
    {
        $this->manager([
            2 => $this->hub('h2', 2, [1 => FatalRule::class])->fatalsStopThisHubOnly()->onFatal($this->hook('h2 onFatal')),
        ])->onFatal($this->hook('manager onFatal'))->sendIt();

        $this->assertSame([
            'h1.p1', 'h1.p2', 'h1 then',
            'FatalRule', 'h2 onFatal(Cannot continue)',
            'h3.p1', 'h3.p2', 'h3 then',
            'h4.p1', 'h4.p2', 'h4 then',
            'h5.p1', 'h5.p2', 'h5 then',
            'manager then',
        ], Journal::$entries);
    }

    public function test_an_error_made_fatal_goes_to_on_fatal_not_on_error(): void
    {
        $previous = null;

        $this->manager([
            2 => $this->hub('h2', 2, [1 => ErrorRule::class])->errorsAreFatal()
                ->onError($this->hook('h2 onError'))
                ->onFatal(function ($subject, $exception) use (&$previous) {
                    Journal::record('h2 onFatal('.$exception->getMessage().')');
                    $previous = $exception;
                }),
        ])->onError($this->hook('manager onError'))->onFatal($this->hook('manager onFatal'))->sendIt();

        $this->assertSame(['h1.p1', 'h1.p2', 'h1 then', 'ErrorRule', 'h2 onFatal(Something is off)', 'manager onFatal(Something is off)'], Journal::$entries);
        $this->assertInstanceOf(PipeRuleFatal::class, $previous);
        $this->assertInstanceOf(PipeRuleError::class, $previous->getPrevious());
    }

    public function test_a_standalone_hubs_on_fatal_runs_once(): void
    {
        $this->hub('hub', 3, [2 => FatalRule::class])->onFatal($this->hook('onFatal'))->sendIt(new stdClass());

        $this->assertSame(['hub.p1', 'FatalRule', 'onFatal(Cannot continue)'], Journal::$entries);
    }

    public function test_hooks_do_not_run_when_nothing_goes_wrong(): void
    {
        $this->manager([
            2 => $this->hub('h2', 2)->onError($this->hook('h2 onError'))->onFatal($this->hook('h2 onFatal')),
        ])->onError($this->hook('manager onError'))->onFatal($this->hook('manager onFatal'))->sendIt();

        $this->assertSame(['h1.p1', 'h1.p2', 'h1 then', 'h2.p1', 'h2.p2', 'h2 then', 'h3.p1', 'h3.p2', 'h3 then', 'h4.p1', 'h4.p2', 'h4 then', 'h5.p1', 'h5.p2', 'h5 then', 'manager then'], Journal::$entries);
    }

    public function test_an_on_error_hook_that_throws_stops_the_hub_as_a_fatal(): void
    {
        $envelope = $this->manager([
            2 => $this->hub('h2', 3, [1 => ErrorRule::class])->onError(fn () => throw new RuntimeException('hook broke')),
        ])->sendIt();

        $this->assertSame(['h1.p1', 'h1.p2', 'h1 then', 'ErrorRule'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
    }

    // ------------------------------------------------------------------
    // Settings

    public function test_the_settings_and_their_getters(): void
    {
        $hub = PipeHub::create();
        $manager = PipeManager::create();

        $this->assertFalse($hub->stopsOnlyThisHubOnFatal());
        $this->assertFalse($hub->treatsErrorsAsFatal());
        $this->assertNull($hub->getOnError());
        $this->assertNull($hub->getOnFatal());
        $this->assertNull($manager->getOnError());
        $this->assertNull($manager->getOnFatal());

        $onError = fn () => null;
        $onFatal = fn () => null;

        $this->assertSame($hub, $hub->fatalsStopThisHubOnly()->errorsAreFatal()->onError($onError)->onFatal($onFatal));
        $this->assertTrue($hub->stopsOnlyThisHubOnFatal());
        $this->assertTrue($hub->treatsErrorsAsFatal());
        $this->assertSame($onError, $hub->getOnError());
        $this->assertSame($onFatal, $hub->getOnFatal());

        $this->assertSame($manager, $manager->onError($onError)->onFatal($onFatal));
        $this->assertSame($onError, $manager->getOnError());
        $this->assertSame($onFatal, $manager->getOnFatal());

        $hub->fatalsStopThisHubOnly(false)->errorsAreFatal(false);
        $this->assertFalse($hub->stopsOnlyThisHubOnFatal());
        $this->assertFalse($hub->treatsErrorsAsFatal());
    }

    public function test_the_removed_settings_are_gone(): void
    {
        foreach (['stopOnFatal', 'stopOnError', 'failureIsFatal', 'onFailure', 'stopsOnFatal', 'stopsOnError', 'isFailureFatal', 'getOnFailure'] as $method) {
            $this->assertFalse(method_exists(PipeHub::class, $method), "PipeHub::{$method}() should be gone");
        }

        foreach (['setStopOnError', 'withoutErrorHandling'] as $method) {
            $this->assertFalse(method_exists(PipeRule::class, $method), "PipeRule::{$method}() should be gone");
        }
    }
}
