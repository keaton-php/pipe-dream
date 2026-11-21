<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Tests\Fixtures\CountingPersist;
use Keaton\PipeDream\Tests\Fixtures\Rules\FatalRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SecondRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ThirdRule;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

class PersistenceTest extends TestCase
{
    private CountingPersist $persist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->persist = new CountingPersist();
        $this->app->instance(PipeLoggerPersistStrategy::class, $this->persist);
    }

    private function hubs(): array
    {
        return [
            PipeHub::create()->addPipe(FirstRule::class),
            PipeHub::create()->addPipe(SecondRule::class),
            PipeHub::create()->addPipe(ThirdRule::class),
        ];
    }

    public function test_a_manager_persists_once_at_the_end_of_the_flow(): void
    {
        $envelope = PipeManager::create($this->hubs())->subject(new stdClass())->sendIt();

        $this->assertSame(1, $this->persist->calls);
        // The single write holds the complete log, manager records included.
        $this->assertSame([count($envelope->logs())], $this->persist->recordCounts);
    }

    public function test_a_manager_persists_once_when_a_fatal_ends_the_flow(): void
    {
        PipeManager::create([
            PipeHub::create()->addPipe(FatalRule::class),
            PipeHub::create()->addPipe(SecondRule::class),
        ])->subject(new stdClass())->sendIt();

        $this->assertSame(1, $this->persist->calls);
    }

    public function test_a_manager_persists_once_when_its_then_callback_throws(): void
    {
        // An exception in then() is recorded as an error; sendIt() still returns.
        $envelope = PipeManager::create($this->hubs())
            ->subject(new stdClass())
            ->then(fn () => throw new \LogicException('then failed'))
            ->sendIt();

        $this->assertTrue($envelope->hasErrors());
        $this->assertSame(1, $this->persist->calls);
    }

    public function test_a_standalone_hub_persists_once(): void
    {
        PipeHub::create()->addPipes([FirstRule::class, SecondRule::class])->sendIt(new stdClass());

        $this->assertSame(1, $this->persist->calls);
    }

    public function test_persistence_can_be_turned_off(): void
    {
        PipeManager::create($this->hubs())->persistLogs(false)->subject(new stdClass())->sendIt();
        PipeHub::create()->addPipe(FirstRule::class)->persistLogs(false)->sendIt(new stdClass());

        $this->assertSame(0, $this->persist->calls);
    }

    public function test_an_envelope_strategy_wins_over_the_container_binding(): void
    {
        $own = new CountingPersist();

        PipeManager::create($this->hubs())->subject(PipeEnvelope::wrap(new stdClass())->persistWith($own))->sendIt();

        $this->assertSame(1, $own->calls);
        $this->assertSame(0, $this->persist->calls);
    }

    public function test_persist_with_accepts_a_class_name(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass())->persistWith(CountingPersist::class);

        $this->assertSame(1, $envelope->persist());
    }

    public function test_persist_with_rejects_a_class_that_is_not_a_strategy(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PipeEnvelope::wrap(new stdClass())->persistWith(stdClass::class);
    }
}
