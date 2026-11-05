<?php

namespace Keaton\PipeDream\Tests\Feature;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Route;
use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\Tests\Fixtures\CountingPersist;
use Keaton\PipeDream\Tests\Fixtures\FakeUrlGenerator;
use Keaton\PipeDream\Tests\Fixtures\Rules\UnhandledExplodingRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\RouteRedirectRule;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

class RouteRedirectTest extends TestCase
{
    private function redirectFrom(callable $run): PipeRuleRedirect
    {
        try {
            $run();
        } catch (PipeRuleRedirect $e) {
            return $e;
        }

        $this->fail('The redirect should have propagated.');
    }

    public function test_a_route_redirect_resolves_through_the_application_by_default(): void
    {
        Route::get('/landing/{campaign}', fn () => 'ok')->name('landing');

        $e = $this->redirectFrom(fn () => PipeHub::create()->addPipe(RouteRedirectRule::class)->sendIt(new stdClass()));

        $this->assertSame('http://localhost/landing/spring', $e->redirectTo);
        $this->assertSame('Redirecting to [http://localhost/landing/spring]', $e->getMessage());
    }

    public function test_a_route_redirect_resolves_through_the_hub_container(): void
    {
        $container = new Container();
        $container->instance('url', new FakeUrlGenerator());

        $e = $this->redirectFrom(fn () => PipeManager::create([
            PipeHub::create($container)->addPipe(RouteRedirectRule::class),
        ], $container)->subject(new stdClass())->sendIt());

        // The application has no 'landing' route; only the custom container could build this.
        $this->assertSame('https://custom.test/landing?campaign=spring', $e->redirectTo);
    }

    public function test_a_route_redirect_from_a_then_callback_resolves_through_the_hub_container(): void
    {
        $container = new Container();
        $container->instance('url', new FakeUrlGenerator());

        $e = $this->redirectFrom(fn () => PipeHub::create($container)
            ->then(fn ($subject) => PipeRuleRedirect::toRoute('thanks', $subject))
            ->sendIt(new stdClass()));

        $this->assertSame('https://custom.test/thanks', $e->redirectTo);
    }

    public function test_a_route_redirect_from_the_manager_then_callback_resolves_through_its_container(): void
    {
        $container = new Container();
        $container->instance('url', new FakeUrlGenerator());

        $e = $this->redirectFrom(fn () => PipeManager::create([PipeHub::create($container)], $container)
            ->subject(new stdClass())
            ->then(fn ($subject) => PipeRuleRedirect::toRoute('done', $subject))
            ->sendIt());

        $this->assertSame('https://custom.test/done', $e->redirectTo);
    }

    private function customContainer(): Container
    {
        $container = new Container();
        $container->instance('url', new FakeUrlGenerator());

        return $container;
    }

    public function test_a_route_redirect_from_on_failure_resolves_in_a_standalone_hub(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass());

        $e = $this->redirectFrom(fn () => PipeHub::create($this->customContainer())
            ->addPipe(UnhandledExplodingRule::class)
            ->onFailure(fn ($subject) => PipeRuleRedirect::toRoute('sorry', $subject))
            ->sendIt($envelope));

        $this->assertSame('https://custom.test/sorry', $e->redirectTo);
        // The hub resolved and logged it, not just the defensive catch on the way out.
        $this->assertNotNull($envelope->logger()->infos()->firstWhere('data', 'Redirecting to https://custom.test/sorry'));
    }

    public function test_a_route_redirect_from_on_failure_resolves_inside_a_manager(): void
    {
        $container = $this->customContainer();
        $persist = new CountingPersist();

        $e = $this->redirectFrom(fn () => PipeManager::create([
            PipeHub::create($container)
                ->addPipe(UnhandledExplodingRule::class)
                ->onFailure(fn ($subject) => PipeRuleRedirect::toRoute('sorry', $subject, ['code' => 7])),
        ])->subject(PipeEnvelope::wrap(new stdClass())->persistWith($persist))->sendIt());

        // The manager runs in the application, which has no 'sorry' route: only
        // the hub's container could have built this URL.
        $this->assertSame('https://custom.test/sorry?code=7', $e->redirectTo);
        $this->assertSame(1, $persist->calls);
    }

    public function test_a_route_redirect_from_on_failure_resolves_through_the_application_by_default(): void
    {
        Route::get('/sorry', fn () => 'sorry')->name('sorry');

        $e = $this->redirectFrom(fn () => PipeHub::create()
            ->addPipe(UnhandledExplodingRule::class)
            ->onFailure(fn ($subject) => PipeRuleRedirect::toRoute('sorry', $subject))
            ->sendIt(new stdClass()));

        $this->assertSame('http://localhost/sorry', $e->redirectTo);
    }

    public function test_a_url_redirect_is_resolved_from_the_start(): void
    {
        $e = new PipeRuleRedirect('https://example.test');

        $this->assertTrue($e->isResolved());
        $this->assertSame('https://example.test', $e->resolveRoute(new Container())->redirectTo);
    }
}
