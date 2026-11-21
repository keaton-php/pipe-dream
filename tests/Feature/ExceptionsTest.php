<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\Exceptions\PipeRuleLoadView;
use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Tests\Fixtures\CountingPersist;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\LimitHandler;
use Keaton\PipeDream\Tests\Fixtures\LimitReached;
use Keaton\PipeDream\Tests\Fixtures\QuotaExceeded;
use Keaton\PipeDream\Tests\Fixtures\Rules\CustomExceptionRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\LoadViewRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\RedirectRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SecondRule;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

class ExceptionsTest extends TestCase
{
    private function flowWith(string $rule, CountingPersist $persist): PipeManager
    {
        $envelope = PipeEnvelope::wrap(new stdClass())->persistWith($persist);

        return PipeManager::create([
            PipeHub::create()->addPipes([FirstRule::class, $rule]),
            PipeHub::create()->addPipe(SecondRule::class),
        ])->subject($envelope)->then(fn () => Journal::record('manager then'));
    }

    public function test_a_redirect_propagates_out_of_the_manager_and_the_log_is_still_persisted(): void
    {
        $persist = new CountingPersist();

        try {
            $this->flowWith(RedirectRule::class, $persist)->sendIt();
            $this->fail('The redirect should have propagated.');
        } catch (PipeRuleRedirect $e) {
            $this->assertSame('https://example.test/landing', $e->redirectTo);
            $this->assertSame('Redirecting to [https://example.test/landing]', $e->getMessage());
        }

        $this->assertSame(['FirstRule', 'RedirectRule'], Journal::$entries);
        $this->assertSame(1, $persist->calls);
    }

    public function test_a_load_view_propagates_out_of_the_manager(): void
    {
        $persist = new CountingPersist();

        try {
            $this->flowWith(LoadViewRule::class, $persist)->sendIt();
            $this->fail('The view should have propagated.');
        } catch (PipeRuleLoadView $e) {
            $this->assertSame('clicks.unavailable', $e->view);
            $this->assertSame(['reason' => 'paused'], $e->data);
            $this->assertSame('Loading view [clicks.unavailable]', $e->getMessage());
        }

        $this->assertSame(['FirstRule', 'LoadViewRule'], Journal::$entries);
        $this->assertSame(1, $persist->calls);
    }

    public function test_exception_handlers_match_subclasses(): void
    {
        $this->app->instance('pipehub:exceptions', [LimitReached::class => LimitHandler::class]);

        PipeHub::create()->addPipe(CustomExceptionRule::class)->sendIt(new stdClass());

        $this->assertSame(['handled '.QuotaExceeded::class], Journal::$entries);
    }

    public function test_handlers_can_be_added_on_the_envelope(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass())->addExceptionHandler(\RuntimeException::class, LimitHandler::class);

        PipeHub::create()->addPipe(CustomExceptionRule::class)->sendIt($envelope);

        $this->assertSame(['handled '.QuotaExceeded::class], Journal::$entries);
    }
}
