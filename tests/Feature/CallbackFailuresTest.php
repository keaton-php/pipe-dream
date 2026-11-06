<?php

namespace Keaton\PipeDream\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Exceptions\PipeRuleFatal;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Tests\Fixtures\CountingPersist;
use Keaton\PipeDream\Tests\Fixtures\Events\AfterHub;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\ExplodingRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FatalRule;
use Keaton\PipeDream\Tests\TestCase;
use LogicException;
use RuntimeException;
use stdClass;

/**
 * What happens when a callback rather than a pipe goes wrong: then() is
 * handled like a pipe, a failing onFatal() hook never changes how the stop
 * ends, and a hub can be run again, for another subject, from inside its
 * own run.
 */
class CallbackFailuresTest extends TestCase
{
    private CountingPersist $persist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->persist = new CountingPersist();
        $this->app->instance(PipeLoggerPersistStrategy::class, $this->persist);
    }

    private function manager(PipeHub $middle): PipeManager
    {
        return PipeManager::create([
            PipeHub::create()->name('first')->addPipe(fn () => Journal::record('first.p1')),
            $middle->name('middle'),
            PipeHub::create()->name('last')->addPipe(fn () => Journal::record('last.p1')),
        ])
            ->subject(new stdClass())
            ->then(fn () => Journal::record('manager then'))
            ->onError(fn ($s, $e) => Journal::record('manager onError: '.$e->getMessage()))
            ->onFatal(fn ($s, $e) => Journal::record('manager onFatal: '.$e->getMessage()));
    }

    // ------------------------------------------------------------------
    // A hub's then()

    public function test_an_exception_in_a_hubs_then_is_an_error_and_the_flow_carries_on(): void
    {
        $envelope = $this->manager(
            PipeHub::create()
                ->addPipe(fn () => Journal::record('middle.p1'))
                ->then(fn () => throw new RuntimeException('then broke'))
                ->onError(fn ($s, $e) => Journal::record('middle onError: '.$e->getMessage()))
        )->sendIt();

        $this->assertSame(['first.p1', 'middle.p1', 'middle onError: then broke', 'manager onError: then broke', 'last.p1', 'manager then'], Journal::$entries);
        $this->assertTrue($envelope->hasErrors());
        $this->assertFalse($envelope->hasFatalErrors());
    }

    public function test_with_errors_are_fatal_an_exception_in_a_hubs_then_stops_the_flow(): void
    {
        $envelope = $this->manager(
            PipeHub::create()
                ->addPipe(fn () => Journal::record('middle.p1'))
                ->then(fn () => throw new RuntimeException('then broke'))
                ->errorsAreFatal()
                ->onFatal(fn ($s, $e) => Journal::record('middle onFatal: '.get_class($e->getPrevious())))
        )->sendIt();

        $this->assertSame(['first.p1', 'middle.p1', 'middle onFatal: RuntimeException', 'manager onFatal: then broke'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
    }

    public function test_a_fatal_thrown_from_a_hubs_then_stops_the_flow_unless_contained(): void
    {
        $envelope = $this->manager(PipeHub::create()->then(fn ($s) => throw new PipeRuleFatal('from then', $s)))->sendIt();
        $this->assertSame(['first.p1', 'manager onFatal: from then'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());

        Journal::reset();

        $this->manager(PipeHub::create()->then(fn ($s) => throw new PipeRuleFatal('from then', $s))->fatalsStopThisHubOnly())->sendIt();
        $this->assertSame(['first.p1', 'last.p1', 'manager then'], Journal::$entries);
    }

    // ------------------------------------------------------------------
    // The manager's then()

    public function test_an_exception_in_the_managers_then_is_an_error_and_send_it_returns(): void
    {
        $envelope = PipeManager::create([PipeHub::create()->addPipe(fn () => Journal::record('p1'))])
            ->subject(new stdClass())
            ->then(fn () => throw new LogicException('then failed'))
            ->onError(fn ($s, $e) => Journal::record('onError: '.$e->getMessage()))
            ->throwFatals()
            ->sendIt();

        $this->assertSame(['p1', 'onError: then failed'], Journal::$entries);
        $this->assertSame(LogicException::class, $envelope->logger()->errors()->firstWhere('key', 'PipeManager:then')['context']['exception']);
        $this->assertFalse($envelope->hasFatalErrors());
        $this->assertSame(1, $this->persist->calls);
    }

    public function test_a_fatal_thrown_from_the_managers_then_stops_the_flow_as_a_fatal(): void
    {
        $build = fn () => PipeManager::create([PipeHub::create()->addPipe(fn () => Journal::record('p1'))])
            ->subject(new stdClass())
            ->then(fn () => throw new PipeRuleFatal('then says stop'))
            ->onFatal(fn ($s, $e) => Journal::record('onFatal: '.$e->getMessage()));

        // Quiet by default, and recorded as a fatal.
        $this->assertTrue($build()->sendIt()->hasFatalErrors());
        $this->assertSame(['p1', 'onFatal: then says stop'], Journal::$entries);

        Journal::reset();

        try {
            $build()->throwFatals()->sendIt();
            $this->fail('The fatal from then() should have been thrown');
        } catch (PipeRuleFatal $e) {
            $this->assertSame('then says stop', $e->getMessage());
            $this->assertInstanceOf(PipeEnvelope::class, $e->subject());
        }

        $this->assertSame(['p1', 'onFatal: then says stop'], Journal::$entries);
        $this->assertSame(2, $this->persist->calls);
    }

    public function test_when_the_managers_on_error_fails_on_a_then_error_the_flow_stops_as_a_fatal(): void
    {
        $envelope = PipeManager::create([PipeHub::create()])
            ->subject(new stdClass())
            ->then(fn () => throw new LogicException('then failed'))
            ->onError(fn () => throw new RuntimeException('onError failed'))
            ->onFatal(fn ($s, $e) => Journal::record('onFatal: '.get_class($e->getPrevious())))
            ->sendIt();

        $this->assertSame(['onFatal: RuntimeException'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
    }

    // ------------------------------------------------------------------
    // An onFatal() hook that throws

    public function test_a_failing_on_fatal_on_a_standalone_hub_is_recorded_and_the_return_stays_quiet(): void
    {
        $envelope = PipeHub::create()
            ->addPipe(FatalRule::class)
            ->onFatal(fn () => throw new RuntimeException('hook broke'))
            ->sendIt(new stdClass());

        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertStringContainsString('hook broke', $envelope->logger()->fatals()->firstWhere('key', 'PipeHub:onFatal')['data']);
        $this->assertSame(1, $this->persist->calls);
    }

    public function test_a_failing_on_fatal_on_a_standalone_hub_still_lets_the_original_fatal_be_thrown(): void
    {
        try {
            PipeHub::create()
                ->addPipe(FatalRule::class)
                ->onFatal(fn () => throw new RuntimeException('hook broke'))
                ->throwFatals()
                ->sendIt(new stdClass());
            $this->fail('The fatal should have been thrown');
        } catch (PipeRuleFatal $e) {
            $this->assertSame('Cannot continue', $e->getMessage());
        }
    }

    public function test_a_failing_on_fatal_on_a_contained_hub_lets_the_flow_carry_on(): void
    {
        $this->manager(
            PipeHub::create()->addPipe(FatalRule::class)->fatalsStopThisHubOnly()->onFatal(fn () => throw new RuntimeException('hook broke'))
        )->sendIt();

        $this->assertSame(['first.p1', 'FatalRule', 'last.p1', 'manager then'], Journal::$entries);
    }

    public function test_a_failing_on_fatal_on_the_manager_is_recorded_and_the_original_fatal_is_thrown(): void
    {
        try {
            PipeManager::create([PipeHub::create()->addPipe(FatalRule::class)])
                ->subject(new stdClass())
                ->onFatal(fn () => throw new RuntimeException('hook broke'))
                ->throwFatals()
                ->sendIt();
            $this->fail('The fatal should have been thrown');
        } catch (PipeRuleFatal $e) {
            $this->assertSame('Cannot continue', $e->getMessage());
            $this->assertStringContainsString('hook broke', $e->subject()->logger()->fatals()->firstWhere('key', 'PipeManager:onFatal')['data']);
        }

        $this->assertSame(1, $this->persist->calls);
    }

    public function test_an_on_error_hook_can_escalate_an_error_to_a_recorded_fatal(): void
    {
        $envelope = $this->manager(
            PipeHub::create()
                ->addPipes([ExplodingRule::class, fn () => Journal::record('middle.p2')])
                ->onError(fn ($s, $e) => throw new PipeRuleFatal('escalated: '.$e->getMessage()))
        )->sendIt();

        $this->assertSame(['first.p1', 'ExplodingRule', 'manager onFatal: escalated: Boom'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
    }

    public function test_a_configuration_error_from_any_hook_or_then_leaves_send_it_as_itself(): void
    {
        $misconfigured = fn () => PipeHub::create()->addPipe('NoSuchRule');
        $cases = [
            'hub then()' => fn () => PipeHub::create()->then($misconfigured)->sendIt(new stdClass()),
            'hub onError()' => fn () => PipeHub::create()->addPipe(ExplodingRule::class)->onError($misconfigured)->sendIt(new stdClass()),
            'hub onFatal()' => fn () => PipeHub::create()->addPipe(FatalRule::class)->onFatal($misconfigured)->sendIt(new stdClass()),
            'manager then()' => fn () => PipeManager::create([PipeHub::create()])->subject(new stdClass())->then($misconfigured)->sendIt(),
            'manager onFatal()' => fn () => PipeManager::create([PipeHub::create()->addPipe(FatalRule::class)])->subject(new stdClass())->onFatal($misconfigured)->sendIt(),
        ];

        foreach ($cases as $where => $run) {
            try {
                $run();
                $this->fail("A PipeHubException from {$where} should leave sendIt()");
            } catch (\Keaton\PipeDream\Exceptions\PipeHubException $e) {
                $this->assertStringContainsString('NoSuchRule', $e->getMessage(), $where);
            }
        }
    }

    // ------------------------------------------------------------------
    // The subject of a thrown fatal

    public function test_a_thrown_fatal_always_carries_the_envelope(): void
    {
        $theirs = new stdClass();

        try {
            PipeManager::create([PipeHub::create()->addPipe(fn () => throw new PipeRuleFatal('Mine', $theirs))])
                ->subject(new stdClass())
                ->throwFatals()
                ->sendIt();
            $this->fail('The fatal should have been thrown');
        } catch (PipeRuleFatal $e) {
            $this->assertInstanceOf(PipeEnvelope::class, $e->subject());
            $this->assertTrue($e->subject()->hasFatalErrors());
        }
    }

    // ------------------------------------------------------------------
    // The same hub, run again from inside its own run

    public function test_a_nested_run_of_the_same_hub_leaves_the_outer_run_intact(): void
    {
        Event::fake([AfterHub::class]);

        $outer = PipeEnvelope::wrap(new stdClass())->set('name', 'outer');
        $inner = PipeEnvelope::wrap(new stdClass())->set('name', 'inner');

        $hub = PipeHub::create()->name('reentrant')->broadcastEventAfter(AfterHub::class)->persistLogs(false);
        $hub->addPipe(function ($envelope) use (&$hub, $inner) {
            if ($envelope->get('name') === 'outer') {
                $hub->sendIt($inner);
            }
        });

        $hub->sendIt($outer);

        // Each envelope has its own complete log...
        foreach ([$outer, $inner] as $envelope) {
            $finals = array_values(array_filter($envelope->logs(), fn ($r) => $r['data'] === 'Finalized result!'));
            $this->assertCount(1, $finals, $envelope->get('name'));
        }

        // ...each 'after' event carried its own envelope...
        $seen = [];
        Event::assertDispatched(AfterHub::class, function (AfterHub $event) use (&$seen) {
            $seen[] = $event->envelope->get('name');

            return true;
        });
        $this->assertSame(['inner', 'outer'], $seen);

        // ...and the hub describes the run that finished last.
        $this->assertSame($outer, $hub->getSubject());
    }
}
