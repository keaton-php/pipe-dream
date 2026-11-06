<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Exceptions\PipeHubException;
use Keaton\PipeDream\Exceptions\PipeRuleFatal;
use Keaton\PipeDream\Exceptions\PipeRuleLoadView;
use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\CountingPersist;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FatalRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\LoadViewRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\RedirectRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SecondRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ThirdRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ExplodingRule;
use Keaton\PipeDream\Tests\TestCase;
use RuntimeException;
use stdClass;

/**
 * throwFatals(): opt-in, set on the outermost level (the manager, or a hub
 * run on its own). Only a fatal that stops the flow is thrown (never one
 * contained by fatalsStopThisHubOnly()), and only after everything that
 * happens without it: the fatal is logged, onFatal() runs, then() is skipped
 * and the log is persisted.
 */
class ThrowFatalsTest extends TestCase
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
            PipeHub::create()->name('Before')->addPipe(FirstRule::class),
            $middle->name('Middle')->then(fn () => Journal::record('middle then')),
            PipeHub::create()->name('After')->addPipe(ThirdRule::class),
        ])
            ->subject(new stdClass())
            ->then(fn () => Journal::record('manager then'));
    }

    /** Run, expecting a PipeRuleFatal; note what was persisted when it was caught. */
    private function catchFatal(callable $run): PipeRuleFatal
    {
        try {
            $run();
        } catch (PipeRuleFatal $e) {
            Journal::record("caught after {$this->persist->calls} persist");

            return $e;
        }

        $this->fail('No PipeRuleFatal left sendIt()');
    }

    // ------------------------------------------------------------------
    // The option

    public function test_it_is_off_by_default_and_can_be_switched_on_and_off(): void
    {
        $manager = PipeManager::create();
        $hub = PipeHub::create();

        $this->assertFalse($manager->throwsFatals());
        $this->assertFalse($hub->throwsFatals());

        $this->assertSame($manager, $manager->throwFatals());
        $this->assertSame($hub, $hub->throwFatals());
        $this->assertTrue($manager->throwsFatals());
        $this->assertTrue($hub->throwsFatals());

        $manager->throwFatals(false);
        $hub->throwFatals(false);
        $this->assertFalse($manager->throwsFatals());
        $this->assertFalse($hub->throwsFatals());
    }

    // ------------------------------------------------------------------
    // A manager

    public function test_a_manager_throws_the_fatal_that_ended_the_flow_after_persisting(): void
    {
        $e = $this->catchFatal(fn () => $this->manager(
            PipeHub::create()->addPipes([FatalRule::class, SecondRule::class])
        )->throwFatals()->sendIt());

        // No later rule or hub, neither then(), and the log was saved before the catch ran.
        $this->assertSame(['FirstRule', 'FatalRule', 'caught after 1 persist'], Journal::$entries);
        $this->assertSame('Cannot continue', $e->getMessage());

        $envelope = $e->subject();
        $this->assertInstanceOf(PipeEnvelope::class, $envelope);
        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertSame(PipeRule::FATAL_FAIL, $envelope->get(FatalRule::class));

        // Logged exactly as without throwFatals(), through the manager's last line.
        $this->assertSame('flow', $envelope->logger()->warnings()->firstWhere('key', 'PipeHub:sendIt')['context']['ends']);
        $this->assertSame('Flow stopped by a fatal: Cannot continue', $envelope->logger()->warnings()->firstWhere('key', 'PipeManager:sendIt')['data']);
        $logs = $envelope->logs();
        $this->assertSame(['PipeManager:sendIt', 'Finalized result!'], [end($logs)['key'], end($logs)['data']]);

        // The persisted write held the whole log.
        $this->assertSame([count($logs)], $this->persist->recordCounts);
    }

    public function test_a_manager_rethrows_the_same_fatal_instance(): void
    {
        $thrown = new PipeRuleFatal('By hand');

        $caught = $this->catchFatal(fn () => PipeManager::create([
            PipeHub::create()->addPipe(fn () => throw $thrown),
        ])->subject(new stdClass())->throwFatals()->sendIt());

        $this->assertSame($thrown, $caught);
        // Constructed without a subject, it is given the envelope.
        $this->assertInstanceOf(PipeEnvelope::class, $caught->subject());
        $this->assertTrue($caught->subject()->hasFatalErrors());
    }

    public function test_a_fatal_that_already_has_a_subject_keeps_it(): void
    {
        $mine = new stdClass();

        $caught = $this->catchFatal(fn () => PipeManager::create([
            PipeHub::create()->addPipe(fn () => throw new PipeRuleFatal('Mine', $mine)),
        ])->subject(new stdClass())->throwFatals()->sendIt());

        $this->assertSame($mine, $caught->subject());
    }

    public function test_a_contained_fatal_is_not_thrown(): void
    {
        $envelope = $this->manager(
            PipeHub::create()->addPipes([FatalRule::class, SecondRule::class])->fatalsStopThisHubOnly()
        )->throwFatals()->sendIt();

        // Exactly as without throwFatals(): the flow carries on and completes.
        $this->assertSame(['FirstRule', 'FatalRule', 'ThirdRule', 'manager then'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertSame(1, $this->persist->calls);
    }

    public function test_a_contained_fatal_is_not_thrown_when_a_later_hub_ends_cleanly(): void
    {
        $envelope = PipeManager::create([
            PipeHub::create()->addPipe(FatalRule::class)->fatalsStopThisHubOnly(),
            PipeHub::create()->addPipe(SecondRule::class),
        ])->subject(new stdClass())->throwFatals()->sendIt();

        $this->assertSame(['FatalRule', 'SecondRule'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
    }

    public function test_a_manager_throws_the_fatal_errors_are_fatal_raises(): void
    {
        $e = $this->catchFatal(fn () => $this->manager(
            PipeHub::create()
                ->addPipes([ExplodingRule::class, SecondRule::class])
                ->errorsAreFatal()
                ->onFatal(fn () => Journal::record("onFatal after {$this->persist->calls} persist"))
        )->throwFatals()->sendIt());

        $this->assertSame(['FirstRule', 'ExplodingRule', 'onFatal after 0 persist', 'caught after 1 persist'], Journal::$entries);
        $this->assertSame('Boom', $e->getMessage());
        $this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
        $this->assertSame('Boom', $e->getPrevious()->getMessage());
        $this->assertSame(PipeRule::FATAL_FAIL, $e->subject()->get(ExplodingRule::class));
    }

    public function test_hooks_run_before_the_log_is_saved_and_the_fatal_thrown(): void
    {
        $this->catchFatal(fn () => $this->manager(
            PipeHub::create()->addPipe(FatalRule::class)->onFatal(fn () => Journal::record("hub onFatal after {$this->persist->calls} persist"))
        )->onFatal(fn () => Journal::record("manager onFatal after {$this->persist->calls} persist"))->throwFatals()->sendIt());

        $this->assertSame(['FirstRule', 'FatalRule', 'hub onFatal after 0 persist', 'manager onFatal after 0 persist', 'caught after 1 persist'], Journal::$entries);
    }

    public function test_an_exception_that_is_not_fatal_is_not_thrown(): void
    {
        $envelope = $this->manager(
            PipeHub::create()->addPipes([ExplodingRule::class, SecondRule::class])
        )->throwFatals()->sendIt();

        $this->assertSame(['FirstRule', 'ExplodingRule', 'SecondRule', 'middle then', 'ThirdRule', 'manager then'], Journal::$entries);
        $this->assertTrue($envelope->hasErrors());
        $this->assertFalse($envelope->hasFatalErrors());
    }

    public function test_an_error_is_not_thrown(): void
    {
        $envelope = $this->manager(
            PipeHub::create()->addPipes([ErrorRule::class, SecondRule::class])
        )->throwFatals()->sendIt();

        $this->assertSame(['FirstRule', 'ErrorRule', 'SecondRule', 'middle then', 'ThirdRule', 'manager then'], Journal::$entries);
        $this->assertTrue($envelope->hasErrors());
    }

    public function test_a_manager_with_no_fatal_returns_the_envelope(): void
    {
        $envelope = $this->manager(PipeHub::create()->addPipe(SecondRule::class))->throwFatals()->sendIt();

        $this->assertInstanceOf(PipeEnvelope::class, $envelope);
        $this->assertSame(['FirstRule', 'SecondRule', 'middle then', 'ThirdRule', 'manager then'], Journal::$entries);
    }

    public function test_a_manager_still_throws_when_it_does_not_persist(): void
    {
        $this->catchFatal(fn () => PipeManager::create([
            PipeHub::create()->addPipe(FatalRule::class),
        ])->subject(new stdClass())->persistLogs(false)->throwFatals()->sendIt());

        $this->assertSame(['FatalRule', 'caught after 0 persist'], Journal::$entries);
    }

    public function test_by_default_a_manager_returns_the_envelope_after_a_fatal_ends_the_flow(): void
    {
        $envelope = $this->manager(PipeHub::create()->addPipe(FatalRule::class))->sendIt();

        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertSame(['FirstRule', 'FatalRule'], Journal::$entries);
    }

    public function test_a_hubs_own_throw_fatals_is_ignored_inside_a_manager(): void
    {
        // Contained to its hub: the flow carries on.
        $envelope = $this->manager(PipeHub::create()->addPipes([FatalRule::class, SecondRule::class])->fatalsStopThisHubOnly()->throwFatals())->sendIt();
        $this->assertSame(['FirstRule', 'FatalRule', 'ThirdRule', 'manager then'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());

        Journal::reset();

        // Ends the flow, but the manager does not throw it.
        $envelope = $this->manager(PipeHub::create()->addPipe(FatalRule::class)->throwFatals())->sendIt();
        $this->assertSame(['FirstRule', 'FatalRule'], Journal::$entries);
        $this->assertTrue($envelope->hasFatalErrors());
    }

    public function test_a_fatal_thrown_from_the_managers_then_leaves_send_it_even_by_default(): void
    {
        // Not a fatal raised in a hub: it escapes like any exception from then(), after persisting.
        $this->catchFatal(fn () => PipeManager::create([PipeHub::create()->addPipe(FirstRule::class)])
            ->subject(new stdClass())
            ->then(fn () => throw new PipeRuleFatal('From then()'))
            ->sendIt());

        $this->assertSame(['FirstRule', 'caught after 1 persist'], Journal::$entries);
    }

    // ------------------------------------------------------------------
    // A hub run on its own

    public function test_a_standalone_hub_throws_any_fatal_after_persisting(): void
    {
        $hub = PipeHub::create()
            ->addPipes([FirstRule::class, FatalRule::class, SecondRule::class])
            ->then(fn () => Journal::record('hub then'))
            ->throwFatals();

        $e = $this->catchFatal(fn () => $hub->sendIt(new stdClass()));

        $this->assertSame(['FirstRule', 'FatalRule', 'caught after 1 persist'], Journal::$entries);
        $this->assertSame($hub->getSubject(), $e->subject());
        $this->assertTrue($e->subject()->hasFatalErrors());

        $logs = $e->subject()->logs();
        $this->assertSame('flow', $e->subject()->logger()->warnings()->firstWhere('key', 'PipeHub:sendIt')['context']['ends']);
        $this->assertSame(['PipeHub:sendIt', 'Finalized result!'], [end($logs)['key'], end($logs)['data']]);
        $this->assertSame([count($logs)], $this->persist->recordCounts);
    }

    public function test_a_standalone_hub_rethrows_the_same_fatal_instance(): void
    {
        $thrown = new PipeRuleFatal('By hand');

        $caught = $this->catchFatal(fn () => PipeHub::create()->addPipe(fn () => throw $thrown)->throwFatals()->sendIt(new stdClass()));

        $this->assertSame($thrown, $caught);
        $this->assertInstanceOf(PipeEnvelope::class, $caught->subject());
    }

    public function test_a_standalone_hub_throws_with_fatals_stop_this_hub_only_too(): void
    {
        // On its own the hub is the whole flow, so there is nothing to contain the fatal to.
        $this->catchFatal(fn () => PipeHub::create()->addPipe(FatalRule::class)->fatalsStopThisHubOnly()->throwFatals()->sendIt(new stdClass()));

        $this->assertSame(['FatalRule', 'caught after 1 persist'], Journal::$entries);
    }

    public function test_a_standalone_hub_throws_the_fatal_errors_are_fatal_raises(): void
    {
        $e = $this->catchFatal(fn () => PipeHub::create()
            ->addPipe(ExplodingRule::class)
            ->errorsAreFatal()
            ->onFatal(fn () => Journal::record("onFatal after {$this->persist->calls} persist"))
            ->throwFatals()
            ->sendIt(new stdClass()));

        $this->assertSame(['ExplodingRule', 'onFatal after 0 persist', 'caught after 1 persist'], Journal::$entries);
        $this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
    }

    public function test_a_standalone_hub_with_no_fatal_returns_the_envelope(): void
    {
        $envelope = PipeHub::create()->addPipe(FirstRule::class)->throwFatals()->sendIt(new stdClass());

        $this->assertInstanceOf(PipeEnvelope::class, $envelope);
        $this->assertSame(['FirstRule'], Journal::$entries);
    }

    public function test_by_default_a_standalone_hub_returns_the_envelope_after_a_fatal(): void
    {
        $envelope = PipeHub::create()->addPipes([FatalRule::class, SecondRule::class])->sendIt(new stdClass());

        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertSame(['FatalRule'], Journal::$entries);
        $this->assertSame(1, $this->persist->calls);
    }

    public function test_a_standalone_hub_run_twice_without_and_then_with_throw_fatals(): void
    {
        $hub = PipeHub::create()->addPipe(FatalRule::class);

        $this->assertTrue($hub->sendIt(new stdClass())->hasFatalErrors());

        $this->catchFatal(fn () => $hub->throwFatals()->sendIt(new stdClass()));
        $this->assertSame(['FatalRule', 'FatalRule', 'caught after 2 persist'], Journal::$entries);
    }

    // ------------------------------------------------------------------
    // Hand-offs and configuration errors are unchanged

    public function test_redirects_views_and_configuration_errors_leave_as_before(): void
    {
        $cases = [
            PipeRuleRedirect::class => RedirectRule::class,
            PipeRuleLoadView::class => LoadViewRule::class,
        ];

        foreach ($cases as $exception => $rule) {
            foreach ([false, true] as $standalone) {
                try {
                    $standalone
                        ? PipeHub::create()->addPipe($rule)->throwFatals()->sendIt(new stdClass())
                        : PipeManager::create([PipeHub::create()->addPipe($rule)])->subject(new stdClass())->throwFatals()->sendIt();
                    $this->fail("{$exception} did not leave sendIt()");
                } catch (PipeRuleFatal $e) {
                    $this->fail("{$exception} became a fatal");
                } catch (\Throwable $e) {
                    $this->assertInstanceOf($exception, $e);
                }
            }
        }

        $this->expectException(PipeHubException::class);
        PipeManager::create([PipeHub::create()])->throwFatals()->sendIt();
    }
}
