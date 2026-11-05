<?php

namespace Tests;

use R1kon\PipeDream\PipeHub;
use R1kon\PipeDream\PipeRule;
use PHPUnit\Framework\TestCase;
use R1kon\PipeDream\PipeEnvelope;
use Tests\PipeTestCase;

class PipeDreamTest extends TestCase 
{

    
    public $testCase;

    public function setUp(): void
    {
        parent::setUp();

        $this->testCase = new PipeTestCase;
    }

    /**
     * @test
     * A pipe hub can create a pipe
     */
    public function a_pipe_hub_can_be_made()
    {
        $hub = PipeHub::create();

        $this->assertInstanceOf(PipeHub::class, $hub);
    }

    /**
     * @test
     * A pipe hub has a name and a description
     */

     public function a_pipe_hub_has_a_name_and_description()
     {
         $hub = PipeHub::create()
                    ->name("Test pipe")
                    ->description("This is a test pipe");

        $this->assertEquals("Test pipe", $hub->getName());
        $this->assertEquals("This is a test pipe", $hub->getDescription());

     }

     /**
      * @test
      * A pipe hub can add a pipe
      */
     public function a_pipe_hub_can_a_single_pipe()
     {
         $hub = PipeHub::create()
                    ->name("Test pipe")
                    ->description("This is a test pipe")
                    ->addPipe(AddPipeTest::class);

        $this->assertCount(1, $hub->getPipes());
     }

     /**
      * @test
      * A pipe hub can add multiple pipes
      */
        public function a_pipe_hub_can_add_multiple_pipes()
        {
            $hub = PipeHub::create()
                    ->name("Test pipe")
                    ->description("This is a test pipe")
                    ->addPipes([
                        AddPipeTest::class,
                        AddPipeTest::class
                    ]);

            $this->assertCount(2, $hub->getPipes());
        }

    /**
     * @test
     * A pipe hub can create an envelope
     */
    public function a_pipe_hub_can_create_an_envelope()
    {
        $subject = (object) [
            'name' => 'Kevin Eaton',
            'email' => 'kevin@eaton.com',
            'password' => 'pass1234'
        ];

        $hub = PipeHub::create()
                    ->name("Test pipe")
                    ->description("This is a test pipe")
                    ->addPipe(AddPipeTest::class)
                    ->subject($subject);

        $this->assertInstanceOf(PipeEnvelope::class, $hub->getSubject());
    }

    /**
     * @test
     * It can stop adding a pipe if the pipe is invalid
     */
    public function it_can_stop_adding_a_pipe_if_the_pipe_is_invalid()
    {
        $hub = PipeHub::create()
                    ->name("Test pipe")
                    ->description("This is a test pipe")
                    ->addPipe(AddPipeTest::class)
                    ->addPipe("InvalidPipe")
                    ->subject($this->testCase->subject);

        $this->assertCount(1, $hub->getPipes());
    }
}

class AddPipeTest extends PipeRule
{
    public function execute($subject)
    {
        return $subject;
    }
}