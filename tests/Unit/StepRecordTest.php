<?php

namespace Keaton\PipeDream\Tests\Unit;

use Keaton\PipeDream\PipeLogger;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\StepRecord;
use Keaton\PipeDream\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class StepRecordTest extends TestCase
{
    /**
     * A record stream for one rule step: a timer around a result, with a
     * message inside it, under the given key.
     *
     * @return array<int, array<string, mixed>>
     */
    private function stepRecords(string $key, int $result, float $start = 1.000, float $end = 1.003, string $message = 'did a thing'): array
    {
        return [
            ['time' => $start, 'key' => $key, 'type' => PipeLogger::TIMER_START, 'data' => 'start', 'context' => []],
            ['time' => $start, 'key' => $key, 'type' => PipeLogger::INFO, 'data' => $message, 'context' => []],
            ['time' => $start, 'key' => $key, 'type' => PipeLogger::RETURN_VALUE, 'data' => $result, 'context' => []],
            ['time' => $end, 'key' => $key, 'type' => PipeLogger::TIMER_END, 'data' => 'end', 'context' => []],
        ];
    }

    private function loggerWith(array $records): PipeLogger
    {
        return (new PipeLogger())->setRecords($records);
    }

    // ------------------------------------------------------------------
    // The status mapping, pinned result by result (a mutation check): a swap
    // of any arm below flips exactly one of these assertions.

    /** @return array<string, array{int|string, string, string}> */
    public static function resultCases(): array
    {
        return [
            'success' => [PipeRule::SUCCESS, 'ok', 'wasOk'],
            'fail' => [PipeRule::FAIL, 'error', 'hasErrors'],
            'fatal' => [PipeRule::FATAL_FAIL, 'fatal', 'wasFatal'],
            'skip' => [PipeRule::SKIP, 'skipped', 'wasSkipped'],
            'a custom value is ok' => ['published', 'ok', 'wasOk'],
        ];
    }

    /**
     * @dataProvider resultCases
     *
     * @param  int|string  $result
     */
    #[DataProvider('resultCases')]
    public function test_the_status_mapping_is_fixed($result, string $status, string $predicate): void
    {
        $step = new StepRecord('App\\Rules\\DoIt', 'Hub', $result, 1.0, []);

        $this->assertSame($status, $step->status());
        $this->assertTrue($step->{$predicate}(), "{$predicate}() should be true for {$status}");

        // Exactly one of the four predicates is true for any step.
        $trueCount = (int) $step->wasOk() + (int) $step->wasSkipped() + (int) $step->hasErrors() + (int) $step->wasFatal();
        $this->assertSame(1, $trueCount);
    }

    // ------------------------------------------------------------------
    // The value object

    public function test_name_is_the_class_basename_and_rule_is_the_full_key(): void
    {
        $rule = new StepRecord('App\\Rules\\SlugifyTitle', 'Publish', PipeRule::SUCCESS, 2.0, []);
        $this->assertSame('SlugifyTitle', $rule->name());
        $this->assertSame('App\\Rules\\SlugifyTitle', $rule->rule());

        $closure = new StepRecord('Closure@ClickFlow.php:42', null, PipeRule::SUCCESS, 2.0, []);
        $this->assertSame('Closure@ClickFlow.php:42', $closure->name());
        $this->assertSame('Closure@ClickFlow.php:42', $closure->rule());
    }

    public function test_skip_reason_is_the_first_message_only_when_skipped(): void
    {
        $skipped = new StepRecord('R', 'Hub', PipeRule::SKIP, 1.0, ['Nothing to check', 'and another line']);
        $this->assertSame('Nothing to check', $skipped->skipReason());

        $ok = new StepRecord('R', 'Hub', PipeRule::SUCCESS, 1.0, ['ran fine']);
        $this->assertNull($ok->skipReason(), 'a step that ran has no skip reason');

        $silent = new StepRecord('R', 'Hub', PipeRule::SKIP, 1.0, []);
        $this->assertNull($silent->skipReason(), 'a skip with no message has no reason');
    }

    public function test_messages_is_a_collection_of_the_logged_lines(): void
    {
        $step = new StepRecord('R', 'Hub', PipeRule::SUCCESS, 1.0, ['one', 'two']);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $step->messages());
        $this->assertSame(['one', 'two'], $step->messages()->all());
    }

    // ------------------------------------------------------------------
    // steps() derived from records

    public function test_steps_are_one_per_result_record_in_order(): void
    {
        $records = array_merge(
            $this->stepRecords('App\\Rules\\First', PipeRule::SUCCESS),
            $this->stepRecords('App\\Rules\\Second', PipeRule::SKIP, 1.004, 1.005, 'skipped on purpose'),
        );

        $steps = $this->loggerWith($records)->steps();

        $this->assertSame(['First', 'Second'], $steps->map->name()->all());
        $this->assertSame(['ok', 'skipped'], $steps->map->status()->all());
        $this->assertSame('skipped on purpose', $steps->last()->skipReason());
    }

    public function test_hub_lines_are_not_steps_and_the_step_knows_its_hub(): void
    {
        $records = array_merge(
            [
                ['time' => 1.0, 'key' => 'PipeManager:sendIt', 'type' => PipeLogger::INFO, 'data' => 'Sending subject through all hubs', 'context' => ['name' => 'Flow']],
                ['time' => 1.0, 'key' => 'PipeHub:sendIt', 'type' => PipeLogger::INFO, 'data' => 'Sending subject through all pipes', 'context' => ['name' => 'Prepare']],
            ],
            $this->stepRecords('App\\Rules\\TidyTitle', PipeRule::SUCCESS, 1.001, 1.004),
            [
                ['time' => 1.005, 'key' => 'PipeHub:sendIt', 'type' => PipeLogger::INFO, 'data' => 'Sending subject through all pipes', 'context' => ['name' => 'Publish']],
            ],
            $this->stepRecords('App\\Rules\\SlugifyTitle', PipeRule::SUCCESS, 1.006, 1.007),
            [
                ['time' => 1.008, 'key' => 'PipeHub:sendIt', 'type' => PipeLogger::INFO, 'data' => 'Finalized result!', 'context' => ['name' => 'Publish']],
            ],
        );

        $steps = $this->loggerWith($records)->steps();

        $this->assertCount(2, $steps, 'hub and manager lines are not steps');
        $this->assertSame(['TidyTitle', 'SlugifyTitle'], $steps->map->name()->all());
        $this->assertSame(['Prepare', 'Publish'], $steps->map->hub()->all());
    }

    public function test_duration_is_the_timer_span_in_milliseconds(): void
    {
        $step = $this->loggerWith($this->stepRecords('R', PipeRule::SUCCESS, 1.000, 1.003))->steps()->first();

        // 1.003s - 1.000s = 3ms.
        $this->assertEqualsWithDelta(3.0, $step->duration(), 0.0001);
    }

    public function test_a_step_logged_without_timers_has_no_duration(): void
    {
        $records = [
            ['time' => 1.0, 'key' => 'R', 'type' => PipeLogger::INFO, 'data' => 'ran', 'context' => []],
            ['time' => 1.0, 'key' => 'R', 'type' => PipeLogger::RETURN_VALUE, 'data' => PipeRule::SUCCESS, 'context' => []],
        ];

        $step = $this->loggerWith($records)->steps()->first();

        $this->assertNull($step->duration());
        $this->assertSame('ok', $step->status());
    }

    public function test_messages_are_the_lines_logged_inside_the_step(): void
    {
        // An error line is written just after the result record, under the same key.
        $records = [
            ['time' => 1.0, 'key' => 'R', 'type' => PipeLogger::TIMER_START, 'data' => 's', 'context' => []],
            ['time' => 1.0, 'key' => 'R', 'type' => PipeLogger::RETURN_VALUE, 'data' => PipeRule::FAIL, 'context' => []],
            ['time' => 1.0, 'key' => 'R', 'type' => PipeLogger::ERROR, 'data' => 'Something is off', 'context' => []],
            ['time' => 1.001, 'key' => 'R', 'type' => PipeLogger::TIMER_END, 'data' => 'e', 'context' => []],
        ];

        $step = $this->loggerWith($records)->steps()->first();

        $this->assertTrue($step->hasErrors());
        $this->assertSame(['Something is off'], $step->messages()->all());
    }

    public function test_steps_survive_a_json_round_trip(): void
    {
        $records = $this->stepRecords('App\\Rules\\TidyTitle', PipeRule::SUCCESS);

        $live = $this->loggerWith($records)->steps();
        $rebuilt = (new PipeLogger())->rebuild((new PipeLogger())->setRecords($records)->output())->steps();

        $this->assertSame($live->map->name()->all(), $rebuilt->map->name()->all());
        $this->assertSame($live->map->status()->all(), $rebuilt->map->status()->all());
        $this->assertEqualsWithDelta($live->first()->duration(), $rebuilt->first()->duration(), 0.0001);
    }
}
