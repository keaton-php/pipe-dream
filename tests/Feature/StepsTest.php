<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\Models\PipeDreamLog;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeLogger;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\StepRecord;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FatalRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SecondRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SkipsItselfRule;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

/**
 * steps() against real runs: the derivation is driven by actual rules
 * executing, erroring, skipping and stopping, not by hand-written records.
 */
class StepsTest extends TestCase
{
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    /** A two-hub flow that succeeds, errors and skips, and carries on. */
    private function mixedFlow(PipeEnvelope $envelope): PipeManager
    {
        return PipeManager::create([
            PipeHub::create()->name('First stage')->addPipes([FirstRule::class, ErrorRule::class, SkipsItselfRule::class]),
            PipeHub::create()->name('Second stage')->addPipe(SecondRule::class),
        ])->name('A flow')->subject($envelope);
    }

    public function test_steps_reflect_what_really_happened_in_order(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass());

        $this->mixedFlow($envelope)->sendIt();

        $steps = $envelope->steps();

        $this->assertContainsOnlyInstancesOf(StepRecord::class, $steps);
        $this->assertSame(['FirstRule', 'ErrorRule', 'SkipsItselfRule', 'SecondRule'], $steps->map->name()->all());
        $this->assertSame(['ok', 'error', 'skipped', 'ok'], $steps->map->status()->all());
        $this->assertSame(['First stage', 'First stage', 'First stage', 'Second stage'], $steps->map->hub()->all());

        // Every step was timed.
        $this->assertTrue($steps->every(fn (StepRecord $s) => $s->duration() !== null && $s->duration() >= 0));
    }

    public function test_an_errored_step_carries_its_error_line(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass());
        $this->mixedFlow($envelope)->sendIt();

        $error = $envelope->steps()->first(fn (StepRecord $s) => $s->hasErrors());

        $this->assertSame('ErrorRule', $error->name());
        $this->assertStringContainsString('Something is off', $error->messages()->first());
    }

    public function test_a_skipped_step_carries_its_reason(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass());
        $this->mixedFlow($envelope)->sendIt();

        $skip = $envelope->steps()->first(fn (StepRecord $s) => $s->wasSkipped());

        $this->assertSame('SkipsItselfRule', $skip->name());
        $this->assertSame('Nothing to check here', $skip->skipReason());
    }

    public function test_a_fatal_step_is_fatal_and_stops_the_run(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass());

        PipeManager::create([
            PipeHub::create()->name('Guard')->addPipes([FatalRule::class, SecondRule::class]),
        ])->name('Guarded')->subject($envelope)->sendIt();

        $steps = $envelope->steps();

        // The fatal stops the run: SecondRule after it never becomes a step.
        $this->assertSame(['FatalRule'], $steps->map->name()->all());
        $fatal = $steps->first();
        $this->assertTrue($fatal->wasFatal());
        $this->assertSame('fatal', $fatal->status());
        $this->assertStringContainsString('Cannot continue', $fatal->messages()->first());
    }

    public function test_a_closure_step_is_named_for_where_it_was_written(): void
    {
        $envelope = PipeHub::create()
            ->addPipe(fn ($subject) => $subject)
            ->sendIt(PipeEnvelope::wrap(new stdClass()));

        $step = $envelope->steps()->first();

        $this->assertStringStartsWith('Closure@StepsTest.php:', $step->name());
        $this->assertSame('ok', $step->status());
    }

    public function test_the_envelope_delegates_to_the_logger(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass());
        $this->mixedFlow($envelope)->sendIt();

        $this->assertSame(
            $envelope->logger()->steps()->map->name()->all(),
            $envelope->steps()->map->name()->all()
        );
    }

    public function test_the_same_steps_come_back_rebuilt_from_the_database(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass())->persistWith(new DatabasePersistStrategy());

        $this->mixedFlow($envelope)->sendIt();

        $live = $envelope->steps();
        $rebuilt = PipeDreamLog::first()->logger()->steps();

        $this->assertSame($live->map->name()->all(), $rebuilt->map->name()->all());
        $this->assertSame($live->map->status()->all(), $rebuilt->map->status()->all());
        $this->assertSame($live->map->hub()->all(), $rebuilt->map->hub()->all());
        $this->assertSame(
            $live->map->returnValue()->all(),
            $rebuilt->map->returnValue()->all()
        );
        $this->assertSame(
            $live->first(fn (StepRecord $s) => $s->wasSkipped())->skipReason(),
            $rebuilt->first(fn (StepRecord $s) => $s->wasSkipped())->skipReason()
        );
    }

    public function test_without_timers_steps_still_list_but_have_no_duration(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass())->logLevel(PipeLogger::LEVEL_NO_TIMERS);

        $this->mixedFlow($envelope)->sendIt();

        $steps = $envelope->steps();

        $this->assertSame(['FirstRule', 'ErrorRule', 'SkipsItselfRule', 'SecondRule'], $steps->map->name()->all());
        $this->assertTrue($steps->every(fn (StepRecord $s) => $s->duration() === null));
    }

    public function test_the_problems_level_keeps_no_step_result_records(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass())->logLevel(PipeLogger::LEVEL_PROBLEMS);

        $this->mixedFlow($envelope)->sendIt();

        // LEVEL_PROBLEMS keeps only warning/error/fatal lines, so there are no
        // result records to anchor steps on: use logs() for a problems-only view.
        $this->assertCount(0, $envelope->steps());
        $this->assertTrue($envelope->hasErrors());
    }
}
