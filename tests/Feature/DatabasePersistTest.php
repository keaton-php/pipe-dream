<?php

namespace R1kon\PipeDream\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use R1kon\PipeDream\Models\PipeDreamLog;
use R1kon\PipeDream\PipeEnvelope;
use R1kon\PipeDream\PipeHub;
use R1kon\PipeDream\PipeLogger;
use R1kon\PipeDream\PipeManager;
use R1kon\PipeDream\Strategies\DatabasePersistStrategy;
use R1kon\PipeDream\Strategies\PipeLoggerSerializeStrategy;
use R1kon\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\FatalRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\FirstRule;
use R1kon\PipeDream\Tests\Fixtures\Rules\SecondRule;
use R1kon\PipeDream\Tests\Fixtures\Widget;
use R1kon\PipeDream\Tests\TestCase;
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
        $this->assertTrue($row->subject->is($widget));
        $this->assertSame(1, PipeDreamLog::withProblems()->count());
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

    public function test_a_model_that_is_not_a_pipe_dream_log_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DatabasePersistStrategy(null, Widget::class);
    }
}
