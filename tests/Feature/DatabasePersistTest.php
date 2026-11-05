<?php

namespace Keaton\PipeDream\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Keaton\PipeDream\Models\PipeDreamLog;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeLogger;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;
use Keaton\PipeDream\Strategies\PipeLoggerSerializeStrategy;
use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\RedirectRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ReturnsResourceRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FatalRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SecondRule;
use Keaton\PipeDream\Tests\Fixtures\StringKeyWidget;
use Keaton\PipeDream\Tests\Fixtures\Widget;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

class DatabasePersistTest extends TestCase
{
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('string_key_widgets', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }

    private function flow(): PipeManager
    {
        return PipeManager::create([
            PipeHub::create()->name('Check')->addPipes([FirstRule::class, ErrorRule::class]),
            PipeHub::create()->name('Finish')->addPipe(SecondRule::class),
        ])->name('Click intake');
    }

    public function test_the_tests_run_on_in_memory_sqlite(): void
    {
        $this->assertSame('sqlite', $this->app['db']->connection()->getDriverName());
        $this->assertSame(':memory:', $this->app['db']->connection()->getConfig('database'));
    }

    public function test_a_flow_is_persisted_as_one_row(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass())->persistWith(new DatabasePersistStrategy());

        $this->flow()->subject($envelope)->sendIt();

        $this->assertSame(1, PipeDreamLog::count());

        $row = PipeDreamLog::first();
        $this->assertSame($envelope->runId(), $row->run_id);
        $this->assertSame('Click intake', $row->flow);
        $this->assertTrue($row->has_errors);
        $this->assertFalse($row->has_fatals);
        $this->assertNull($row->subject_type);
        $this->assertSame($envelope->logs(), json_decode($row->log, true));
    }

    public function test_persisting_the_same_run_again_updates_its_row(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass())->persistWith(new DatabasePersistStrategy());
        $this->flow()->subject($envelope)->sendIt();
        $firstLength = strlen(PipeDreamLog::first()->log);

        $envelope->logger()->fatal('Later', 'Something found after the flow');
        $envelope->persist();

        $this->assertSame(1, PipeDreamLog::count());
        $row = PipeDreamLog::first();
        $this->assertTrue($row->has_fatals);
        $this->assertGreaterThan($firstLength, strlen($row->log));
    }

    public function test_each_run_gets_its_own_row(): void
    {
        foreach (range(1, 3) as $i) {
            $this->flow()->subject(PipeEnvelope::wrap(new stdClass())->persistWith(new DatabasePersistStrategy()))->sendIt();
        }

        $this->assertSame(3, PipeDreamLog::count());
        $this->assertSame(3, PipeDreamLog::distinct()->count('run_id'));
    }

    public function test_a_stored_log_rebuilds_into_a_logger(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass())->persistWith(new DatabasePersistStrategy());
        $this->flow()->subject($envelope)->sendIt();

        $logger = PipeDreamLog::forRun($envelope->runId())->firstOrFail()->logger();

        $this->assertInstanceOf(PipeLogger::class, $logger);
        $this->assertEquals($envelope->logs(), $logger->records());
        $this->assertCount(1, $logger->errors());
        $this->assertCount(3, $logger->returnValues());
    }

    public function test_an_eloquent_subject_is_stored_as_the_morph_subject(): void
    {
        $widget = Widget::create(['name' => 'Spring sale']);

        PipeHub::create()
            ->name('Widget check')
            ->addPipe(FatalRule::class)
            ->sendIt(PipeEnvelope::wrap($widget)->persistWith(new DatabasePersistStrategy()));

        $row = PipeDreamLog::first();
        $this->assertSame('Widget check', $row->flow);
        $this->assertTrue($row->has_fatals);
        $this->assertSame(Widget::class, $row->subject_type);
        $this->assertSame((string) $widget->id, $row->subject_id);
        $this->assertTrue($row->subject->is($widget));
        $this->assertSame(1, PipeDreamLog::withProblems()->count());
    }

    public function test_a_uuid_keyed_subject_is_stored_and_found_again(): void
    {
        $this->assertStringKeyedSubjectRoundTrips('9b2f6c4e-1d3a-4f5b-8c7d-0e1f2a3b4c5d');
    }

    public function test_a_ulid_keyed_subject_is_stored_and_found_again(): void
    {
        $this->assertStringKeyedSubjectRoundTrips('01JBX8Z6Q4M7W3R2T9V5K8N1PD');
    }

    private function assertStringKeyedSubjectRoundTrips(string $key): void
    {
        $widget = StringKeyWidget::create(['id' => $key, 'name' => 'Spring sale']);

        PipeHub::create()
            ->addPipe(FirstRule::class)
            ->sendIt(PipeEnvelope::wrap($widget)->persistWith(new DatabasePersistStrategy()));

        $row = PipeDreamLog::first();
        $this->assertSame(StringKeyWidget::class, $row->subject_type);
        $this->assertSame($key, $row->subject_id);
        $this->assertTrue($row->subject->is($widget));
    }

    public function test_the_log_column_can_use_the_serialize_format(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass())
            ->persistWith(new DatabasePersistStrategy(new PipeLoggerSerializeStrategy()));
        $this->flow()->subject($envelope)->sendIt();

        $row = PipeDreamLog::first();
        $this->assertStringStartsWith('a:', $row->log);
        $this->assertEquals($envelope->logs(), $row->logger(new PipeLoggerSerializeStrategy())->records());
    }

    public function test_values_json_cannot_hold_never_stop_the_log_being_stored(): void
    {
        $envelope = PipeEnvelope::wrap(new stdClass())->persistWith(new DatabasePersistStrategy());

        try {
            PipeManager::create([
                PipeHub::create()->addPipes([ReturnsResourceRule::class, RedirectRule::class]),
            ])->subject($envelope)->sendIt();

            $this->fail('The redirect should have propagated.');
        } catch (PipeRuleRedirect $e) {
            // The redirect is what the caller sees; persisting did not replace it.
            $this->assertSame('https://example.test/landing', $e->redirectTo);
        }

        $records = PipeDreamLog::forRun($envelope->runId())->firstOrFail()->logger()->records();
        $this->assertContains('resource(stream)', array_column($records, 'data'));
        $this->assertContains(['score' => 'INF', 'ratio' => 'NAN'], array_column($records, 'data'));
    }

    public function test_a_model_that_is_not_a_pipe_dream_log_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DatabasePersistStrategy(null, Widget::class);
    }
}
