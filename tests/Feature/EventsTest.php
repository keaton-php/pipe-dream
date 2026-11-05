<?php

namespace R1kon\PipeDream\Tests\Feature;

use Illuminate\Support\Facades\Event;
use R1kon\PipeDream\Exceptions\PipeHubException;
use R1kon\PipeDream\PipeEnvelope;
use R1kon\PipeDream\PipeHub;
use R1kon\PipeDream\PipeManager;
use R1kon\PipeDream\Tests\Fixtures\Events\AfterHub;
use R1kon\PipeDream\Tests\Fixtures\Events\AfterPipe;
use R1kon\PipeDream\Tests\Fixtures\Events\BeforeHub;
use R1kon\PipeDream\Tests\Fixtures\Events\BeforePipe;
use R1kon\PipeDream\Tests\Fixtures\Events\HubSkipped;
use R1kon\PipeDream\Tests\Fixtures\Journal;
use R1kon\PipeDream\Tests\Fixtures\Rules\FatalRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\FirstRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\SecondRule;
use R1kon\PipeDream\Tests\TestCase;
use stdClass;

class EventsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([BeforeHub::class, AfterHub::class, HubSkipped::class, BeforePipe::class, AfterPipe::class] as $event) {
            Event::listen($event, function ($e) {
                Journal::record(class_basename($e).($e->pipe ? '('.class_basename($e->pipe).')' : ''));
            });
        }
    }

    private function hub(): PipeHub
    {
        return PipeHub::create()
            ->name('Events')
            ->broadcastEventBefore(BeforeHub::class)
            ->broadcastEventAfter(AfterHub::class)
            ->broadcastEventOnSkip(HubSkipped::class)
            ->broadcastEventBeforeEachPipe(BeforePipe::class)
            ->broadcastEventAfterEachPipe(AfterPipe::class);
    }

    public function test_events_fire_in_order_and_after_each_fires_as_each_pipe_hands_off(): void
    {
        $this->hub()
            ->addPipes([FirstRule::class, SecondRule::class])
            ->then(fn () => Journal::record('then'))
            ->sendIt(new stdClass());

        $this->assertSame([
            'BeforeHub',
            'BeforePipe(FirstRule)',
            'FirstRule',
            'AfterPipe(FirstRule)',
            'BeforePipe(SecondRule)',
            'SecondRule',
            'AfterPipe(SecondRule)',
            'AfterHub',
            'then',
        ], Journal::$entries);
    }

    public function test_events_carry_the_envelope_and_the_pipe(): void
    {
        $received = [];
        Event::listen(BeforePipe::class, function (BeforePipe $event) use (&$received) {
            $received[] = $event;
        });

        $envelope = $this->hub()->addPipe(FirstRule::class)->sendIt(new stdClass());

        $this->assertSame($envelope, $received[0]->envelope);
        $this->assertSame(FirstRule::class, $received[0]->pipe);
    }

    public function test_each_dispatch_is_logged_with_its_own_event_type(): void
    {
        $envelope = $this->hub()->addPipe(FirstRule::class)->sendIt(new stdClass());

        $logged = $envelope->logger()->recordsByKey('PipeHub:event')->map(fn ($r) => $r['context']['event'])->values()->all();

        $this->assertSame(['before', 'beforeEach', 'afterEach', 'after'], $logged);
        $this->assertStringContainsString('[afterEach]', $envelope->logger()->recordsByKey('PipeHub:event')->values()[2]['data']);
    }

    public function test_a_pipe_that_stops_the_hub_never_hands_off(): void
    {
        $this->hub()->addPipes([FatalRule::class, SecondRule::class])->sendIt(new stdClass());

        $this->assertSame(['BeforeHub', 'BeforePipe(FatalRule)', 'FatalRule'], Journal::$entries);
    }

    public function test_a_skipped_hub_fires_only_the_skip_event(): void
    {
        $this->hub()->addPipe(FirstRule::class)->skipIf(fn () => true)->sendIt(new stdClass());
        $this->hub()->addPipe(SecondRule::class)->skip()->sendIt(new stdClass());

        $this->assertSame(['HubSkipped', 'HubSkipped'], Journal::$entries);
    }

    public function test_events_work_inside_a_manager(): void
    {
        PipeManager::create([
            $this->hub()->addPipe(FirstRule::class),
            PipeHub::create()->addPipe(SecondRule::class)->broadcastEventAfter(AfterHub::class),
        ])->subject(new stdClass())->sendIt();

        $this->assertSame(['BeforeHub', 'BeforePipe(FirstRule)', 'FirstRule', 'AfterPipe(FirstRule)', 'AfterHub', 'SecondRule', 'AfterHub'], Journal::$entries);
    }

    public function test_an_event_class_that_does_not_exist_throws(): void
    {
        $this->expectException(PipeHubException::class);

        PipeHub::create()->broadcastEventBefore('App\Events\Missing');
    }
}
