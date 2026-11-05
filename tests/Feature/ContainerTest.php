<?php

namespace Keaton\PipeDream\Tests\Feature;

use Illuminate\Container\Container;
use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Tests\Fixtures\CountingPersist;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Rules\InjectedRule;
use Keaton\PipeDream\Tests\Fixtures\Thermometer;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

/**
 * A hub given its own container uses it for everything in the run: making
 * the rules, calling their methods, closure pipes and the envelope.
 */
class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        $this->container->instance(Thermometer::class, new Thermometer(99));
    }

    public function test_rule_methods_and_closure_pipes_resolve_from_the_hub_container(): void
    {
        PipeHub::create($this->container)
            ->addPipes([
                InjectedRule::class,
                fn ($subject, Thermometer $thermometer) => Journal::record('closure '.$thermometer->read()),
            ])
            ->sendIt(new stdClass());

        // The application container would have given 21.
        $this->assertSame(['temperature=99', 'closure 99'], Journal::$entries);
    }

    public function test_the_envelope_a_hub_creates_uses_the_hub_container(): void
    {
        $persist = new CountingPersist();
        $this->container->instance(PipeLoggerPersistStrategy::class, $persist);

        $envelope = PipeHub::create($this->container)->addPipe(InjectedRule::class)->sendIt(new stdClass());

        $this->assertSame($this->container, $envelope->container());
        $this->assertSame(1, $persist->calls);
    }

    public function test_the_envelope_a_manager_creates_uses_the_manager_container(): void
    {
        $envelope = PipeManager::create([PipeHub::create($this->container)->addPipe(InjectedRule::class)], $this->container)
            ->subject(new stdClass())
            ->then(fn ($subject, Thermometer $thermometer) => Journal::record('then '.$thermometer->read()))
            ->sendIt();

        $this->assertSame($this->container, $envelope->container());
        $this->assertSame(['temperature=99', 'then 99'], Journal::$entries);
    }

    public function test_hubs_without_a_container_use_the_application(): void
    {
        PipeHub::create()->addPipe(InjectedRule::class)->sendIt(new stdClass());

        $this->assertSame(['temperature=21'], Journal::$entries);
    }
}
