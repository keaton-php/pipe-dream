<?php

namespace Keaton\PipeDream\Tests\Unit;

use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

class PipeEnvelopeTest extends TestCase
{
    public function test_wrap_reuses_an_existing_envelope(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass());

        $this->assertSame($envelope, PipeEnvelope::wrap($envelope));
    }

    public function test_every_envelope_has_a_stable_uuid_run_id(): void
    {
        $envelope = new PipeEnvelope(new stdClass());

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $envelope->runId());
        $this->assertSame($envelope->runId(), $envelope->runId());
        $this->assertNotSame($envelope->runId(), (new PipeEnvelope(new stdClass()))->runId());
    }

    public function test_saved_values_are_read_exactly_then_case_insensitively(): void
    {
        $envelope = (new PipeEnvelope())->set('botScore', 0.4)->set('flag', false);

        $this->assertSame(0.4, $envelope->get('botScore'));
        $this->assertSame(0.4, $envelope->get('BOTSCORE'));
        $this->assertFalse($envelope->get('flag'));
        $this->assertNull($envelope->get('missing'));
        $this->assertSame(['botScore' => 0.4, 'flag' => false], $envelope->get());
    }

    public function test_falsey_keys_are_real_keys(): void
    {
        $envelope = (new PipeEnvelope())->set(0, 'zero')->set('', 'empty')->set('other', 'x');

        $this->assertSame('zero', $envelope->get(0));
        $this->assertSame('zero', $envelope->get('0'));
        $this->assertSame('empty', $envelope->get(''));
        $this->assertNull((new PipeEnvelope())->get(0));
        $this->assertCount(3, $envelope->get());
        $this->assertCount(3, $envelope->get(null));
    }

    public function test_a_fresh_envelope_has_no_errors(): void
    {
        $envelope = new PipeEnvelope(new stdClass());

        $this->assertFalse($envelope->hasErrors());
        $this->assertFalse($envelope->hasFatalErrors());

        $envelope->logger()->error('Rule', 'bad');

        $this->assertTrue($envelope->hasErrors());
        $this->assertFalse($envelope->hasFatalErrors());
    }

    public function test_method_calls_pass_through_to_the_subject(): void
    {
        $subject = new class {
            public function campaign(): string
            {
                return 'spring-sale';
            }
        };

        $this->assertSame('spring-sale', PipeEnvelope::wrap($subject)->campaign());

        $this->expectException(\BadMethodCallException::class);
        PipeEnvelope::wrap($subject)->nothingHere();
    }
}
