<?php

namespace Keaton\PipeDream\Tests\Unit;

use Keaton\PipeDream\Contracts\PipeLoggerStrategy;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeLogger;
use Keaton\PipeDream\Strategies\PipeLoggerJsonStrategy;
use Keaton\PipeDream\Strategies\PipeLoggerSerializeStrategy;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Thermometer;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

class PipeLoggerTest extends TestCase
{
    private function logEveryType(PipeLogger $logger): PipeLogger
    {
        $logger->timerStart('Rule', 'start');
        $logger->info('Rule', 'info');
        $logger->returnValue('Rule', 1);
        $logger->warning('Rule', 'warning');
        $logger->error('Rule', 'error');
        $logger->fatal('Rule', 'fatal');
        $logger->timerEnd('Rule', 'end');

        return $logger;
    }

    private function types(PipeLogger $logger): array
    {
        return array_column($logger->records(), 'type');
    }

    public function test_it_records_everything_by_default(): void
    {
        $logger = $this->logEveryType(new PipeLogger());

        $this->assertSame(PipeLogger::LEVEL_EVERYTHING, $logger->level());
        $this->assertSame(['timerStart', 'info', 'returnValue', 'warning', 'error', 'fatal', 'timerEnd'], $this->types($logger));
    }

    public function test_the_no_timers_level_drops_timers(): void
    {
        $logger = $this->logEveryType((new PipeLogger())->setLevel(PipeLogger::LEVEL_NO_TIMERS));

        $this->assertSame(['info', 'returnValue', 'warning', 'error', 'fatal'], $this->types($logger));
    }

    public function test_the_problems_level_keeps_only_warnings_errors_and_fatals(): void
    {
        $logger = $this->logEveryType((new PipeLogger())->setLevel(PipeLogger::LEVEL_PROBLEMS));

        $this->assertSame(['warning', 'error', 'fatal'], $this->types($logger));
    }

    public function test_an_unknown_level_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PipeLogger())->setLevel('verbose');
    }

    public function test_the_envelope_log_level_applies_to_a_whole_flow(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass())->logLevel(PipeLogger::LEVEL_PROBLEMS);

        PipeHub::create()->addPipes([FirstRule::class, ErrorRule::class])->sendIt($envelope);

        $this->assertSame(['error'], array_values(array_unique($this->types($envelope->logger()))));
        // Results are still recorded, whatever the level.
        $this->assertCount(2, $envelope->results());
    }

    public function test_output_defaults_to_json(): void
    {
        $logger = $this->logEveryType(new PipeLogger());

        $this->assertInstanceOf(PipeLoggerJsonStrategy::class, $logger->strategy());
        $this->assertSame($logger->records(), json_decode($logger->output(), true));
    }

    public function test_json_round_trip(): void
    {
        $logger = $this->logEveryType(new PipeLogger());
        $logger->info('Rule', 'context', ['country' => 'CA', 'score' => 0.5]);

        $rebuilt = (new PipeLogger())->rebuild($logger->output(PipeLoggerJsonStrategy::class));

        $this->assertSame($logger->records(), $rebuilt->records());
        $this->assertCount(1, $rebuilt->fatals());
        $this->assertCount(2, $rebuilt->timers());
    }

    public function test_serialize_round_trip(): void
    {
        $logger = $this->logEveryType(new PipeLogger());

        $serialized = $logger->output(new PipeLoggerSerializeStrategy());
        $rebuilt = (new PipeLogger())->rebuild($serialized, PipeLoggerSerializeStrategy::class);

        $this->assertSame(serialize($logger->records()), $serialized);
        $this->assertSame($logger->records(), $rebuilt->records());
    }

    public function test_rebuilding_a_serialized_log_never_instantiates_classes(): void
    {
        $logger = new PipeLogger();
        $logger->returnValue('Rule', new Thermometer());

        $rebuilt = (new PipeLogger())->rebuild($logger->output(new PipeLoggerSerializeStrategy()), new PipeLoggerSerializeStrategy());

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $rebuilt->records()[0]['data']);
    }

    public function test_rebuilding_nothing_gives_an_empty_log(): void
    {
        $this->assertSame([], (new PipeLogger())->rebuild('')->records());
        $this->assertSame([], (new PipeLogger())->rebuild(null, new PipeLoggerSerializeStrategy())->records());
    }

    public function test_a_custom_strategy_can_be_used(): void
    {
        $strategy = new class implements PipeLoggerStrategy {
            public function output($records)
            {
                return count($records).' records';
            }

            public function rebuild($records)
            {
                return [];
            }
        };

        $this->assertSame('7 records', $this->logEveryType(new PipeLogger())->output($strategy));
    }

    public function test_a_strategy_that_is_not_a_strategy_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PipeLogger())->setStrategy(stdClass::class);
    }

    public function test_records_can_be_filtered_by_key_and_type(): void
    {
        $logger = $this->logEveryType(new PipeLogger());
        $logger->info('Other', 'info');

        $this->assertCount(7, $logger->recordsByKey('Rule'));
        $this->assertCount(2, $logger->recordsByKey());
        $this->assertCount(2, $logger->infos());
        $this->assertCount(1, $logger->warnings());
        $this->assertCount(1, $logger->errors());
        $this->assertCount(1, $logger->returnValues());
    }
}
