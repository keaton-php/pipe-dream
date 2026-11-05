<?php

namespace Keaton\PipeDream\Tests\Unit;

use Keaton\PipeDream\PipeLogger;
use Keaton\PipeDream\Strategies\PipeLoggerJsonStrategy;
use Keaton\PipeDream\Support\LogValue;
use Keaton\PipeDream\Tests\Fixtures\Thermometer;
use Keaton\PipeDream\Tests\Fixtures\Verdict;
use Keaton\PipeDream\Tests\TestCase;

class LogValueTest extends TestCase
{
    public function test_plain_values_are_kept(): void
    {
        foreach ([null, true, 7, 0.5, 'text', ['a' => [1, 2]]] as $value) {
            $this->assertSame($value, LogValue::normalize($value));
        }
    }

    public function test_values_json_cannot_hold_become_strings(): void
    {
        $stream = fopen('php://memory', 'r');
        $closed = fopen('php://memory', 'r');
        fclose($closed);

        $this->assertSame('INF', LogValue::normalize(INF));
        $this->assertSame('-INF', LogValue::normalize(-INF));
        $this->assertSame('NAN', LogValue::normalize(NAN));
        $this->assertSame('resource(stream)', LogValue::normalize($stream));
        $this->assertSame('resource (closed)', LogValue::normalize($closed));
        $this->assertSame('Closure', LogValue::normalize(fn () => null));
    }

    public function test_invalid_utf8_is_replaced(): void
    {
        $normalized = LogValue::normalize("caf\xE9");

        $this->assertTrue(mb_check_encoding($normalized, 'UTF-8'));
        $this->assertStringStartsWith('caf', $normalized);
    }

    public function test_objects_become_data(): void
    {
        $this->assertSame(['__class' => Thermometer::class, 'degrees' => 30], LogValue::normalize(new Thermometer(30)));
        $this->assertSame('approved', LogValue::normalize(Verdict::Approved));
        $this->assertSame(['exception' => \RuntimeException::class, 'message' => 'Boom'], LogValue::normalize(new \RuntimeException('Boom')));
        $this->assertSame('2026-11-05T12:00:00+00:00', LogValue::normalize(new \DateTimeImmutable('2026-11-05 12:00:00', new \DateTimeZone('UTC'))));
        $this->assertSame(['a' => 1], LogValue::normalize(collect(['a' => 1])));
    }

    public function test_a_cycle_is_cut(): void
    {
        $node = new \stdClass();
        $node->self = $node;

        $this->assertSame(['__class' => \stdClass::class, 'self' => '[recursion: stdClass]'], LogValue::normalize($node));
    }

    public function test_deep_nesting_is_cut(): void
    {
        $deep = 'bottom';
        for ($i = 0; $i < 40; $i++) {
            $deep = [$deep];
        }

        $this->assertStringContainsString('[max depth]', json_encode(LogValue::normalize($deep)));
    }

    public function test_a_log_holding_any_of_these_always_encodes(): void
    {
        $node = new \stdClass();
        $node->self = $node;

        $logger = new PipeLogger();
        $logger->returnValue('Rule', fopen('php://memory', 'r'));
        $logger->info('Rule', ['score' => INF, 'ratio' => NAN, 'node' => $node], ['bad utf8' => "\xB1".'1']);

        $json = $logger->output(new PipeLoggerJsonStrategy());

        $this->assertSame($logger->records(), (new PipeLogger())->rebuild($json)->records());
    }
}
