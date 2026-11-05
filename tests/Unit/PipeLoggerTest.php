<?php

namespace Tests;

use Tests\PipeTestCase;
use PHPUnit\Framework\TestCase;
use R1kon\PipeDream\PipeLogger;
use R1kon\PipeDream\Contracts\PipeLoggerStrategy;

class PipeLoggerTest extends TestCase 
{

    
    public $testCase;

    public function setUp(): void
    {
        parent::setUp();
        $this->testCase = new PipeTestCase;
    }

    /**
     * @test
     * A pipe logger can hold logs
     */
    public function a_pipe_logger_can_hold_logs()
    {
        $logger = new PipeLogger;

        $logger->info("PipeKey", "Info log");
        $logger->warning("PipeKey", "Warning log");
        $logger->error("PipeKey", "Error log");
        $logger->fatal("PipeKey", "Fatal log");

        $this->assertCount(4, $logger->records());

    }

    /**
     * @test
     * A pipe logger can use a strategy to output its logs
     */
    public function a_pipe_logger_can_use_a_strategy_to_output_its_logs()
    {
        $logger = new PipeLogger;

        $logger->info("PipeKey", "Info log");
        $logger->warning("PipeKey", "Warning log");
        $logger->error("PipeKey", "Error log");
        $logger->fatal("PipeKey", "Fatal log");

        $this->assertCount(4, $logger->records());

        $result = $logger->setStrategy(PipeLoggerTestStrategy::class);
        $this->assertEquals("Strategy output", $result->output());
    }

    /**
     * @test
     * It can output logs by type
     */
    public function it_can_output_logs_by_type()
    {
        $logger = new PipeLogger;

        $logger->info("PipeKey", "Info log");
        $logger->info("PipeKey", "Info log");
        $logger->error("PipeKey", "Error log");
        $logger->fatal("PipeKey", "Fatal log");

        $this->assertCount(4, $logger->records());
        $this->assertCount(2, $logger->recordsByType(PipeLogger::INFO));
        $this->assertCount(1, $logger->recordsByType(PipeLogger::ERROR));
        $this->assertCount(1, $logger->recordsByType(PipeLogger::FATAL));
    
    }

    /**
     * @test
     * It can get the serialized version of the logs
     */
    public function it_can_get_the_serialized_version_of_the_logs()
    {
        $logger = new PipeLogger;

        $logger->info("PipeKey", "Info log");
        $logger->info("PipeKey", "Info log");
        $logger->error("PipeKey", "Error log");
        $logger->fatal("PipeKey", "Fatal log");

        $this->assertCount(4, $logger->records());
        $this->assertCount(2, $logger->recordsByType(PipeLogger::INFO));
        $this->assertCount(1, $logger->recordsByType(PipeLogger::ERROR));
        $this->assertCount(1, $logger->recordsByType(PipeLogger::FATAL));

        $serialized = $logger->output(PipeLoggerSerializerTestStrategy::class);
        
        $this->assertEquals(serialize($logger->records()), $serialized);
    }

    /**
     * @test
     * It can json encode and decode the logs
     */
    public function it_can_json_encode_and_decode_the_logs()
    {
        $logger = new PipeLogger;

        $logger->info("PipeKey", "Info log");
        $logger->info("PipeKey", "Info log");
        $logger->error("PipeKey", "Error log");
        $logger->fatal("PipeKey", "Fatal log");

        $this->assertCount(4, $logger->records());
        $this->assertCount(2, $logger->recordsByType(PipeLogger::INFO));
        $this->assertCount(1, $logger->recordsByType(PipeLogger::ERROR));
        $this->assertCount(1, $logger->recordsByType(PipeLogger::FATAL));

        $json = $logger->output(PipeLoggerJsonTestStrategy::class);
        
        $this->assertEquals(json_encode($logger->records()), $json);
    }

    /**
     * @test
     * It can reconstitute a json encoded log
     */
    public function it_can_reconstitute_a_json_encoded_log()
    {
        $logger = new PipeLogger;

        $logger->info("PipeKey", "Info log");
        $logger->info("PipeKey", "Info log");
        $logger->error("PipeKey", "Error log");
        $logger->fatal("PipeKey", "Fatal log");

        $this->assertCount(4, $logger->records());
        $this->assertCount(2, $logger->recordsByType(PipeLogger::INFO));
        $this->assertCount(1, $logger->recordsByType(PipeLogger::ERROR));
        $this->assertCount(1, $logger->recordsByType(PipeLogger::FATAL));

        $json = $logger->output(PipeLoggerJsonTestStrategy::class);
        
        $this->assertEquals(json_encode($logger->records()), $json);

        $newLogger = (new PipeLogger)->rebuild($json, PipeLoggerJsonTestStrategy::class);

        $this->assertCount(4, $newLogger->records());
        $this->assertCount(2, $newLogger->recordsByType(PipeLogger::INFO));
        $this->assertCount(1, $newLogger->recordsByType(PipeLogger::ERROR));
        $this->assertCount(1, $newLogger->recordsByType(PipeLogger::FATAL));
    }


    /**
     * @test
     * It can reconstitute a serialized log
     */
    public function it_can_reconstitute_a_serialized_log()
    {
        $logger = new PipeLogger;

        $logger->info("PipeKey", "Info log");
        $logger->info("PipeKey", "Info log");
        $logger->error("PipeKey", "Error log");
        $logger->fatal("PipeKey", "Fatal log");

        $this->assertCount(4, $logger->records());
        $this->assertCount(2, $logger->recordsByType(PipeLogger::INFO));
        $this->assertCount(1, $logger->recordsByType(PipeLogger::ERROR));
        $this->assertCount(1, $logger->recordsByType(PipeLogger::FATAL));

        $json = $logger->output(PipeLoggerSerializerTestStrategy::class);
        
        $this->assertEquals(serialize($logger->records()), $json);

        $newLogger = (new PipeLogger)->rebuild($json, PipeLoggerSerializerTestStrategy::class);

        $this->assertCount(4, $newLogger->records());
        $this->assertCount(2, $newLogger->recordsByType(PipeLogger::INFO));
        $this->assertCount(1, $newLogger->recordsByType(PipeLogger::ERROR));
        $this->assertCount(1, $newLogger->recordsByType(PipeLogger::FATAL));
    }
}

class PipeLoggerTestStrategy implements PipeLoggerStrategy
{
    public function output($records)
    {
        return "Strategy output";
    }
    public function rebuild($records)
    {
        return $records;
    }
}

class PipeLoggerSerializerTestStrategy implements PipeLoggerStrategy
{
    public function output($records)
    {
        return serialize($records);
    }
    public function rebuild($records)
    {
        return unserialize($records);
    }
}

class PipeLoggerJsonTestStrategy implements PipeLoggerStrategy
{
    public function output($records)
    {
        return json_encode($records);
    }
    public function rebuild($records)
    {
        return json_decode($records, true);
    }
}