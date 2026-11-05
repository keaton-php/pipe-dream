<?php
namespace Tests;

use Orchestra\Testbench\TestCase;

use R1kon\PipeDream\PipeHub;
use R1kon\PipeDream\PipeManager;
use R1kon\PipeDream\PipeRule;

class PipeManagerTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     * A pipe manager can have many pipe hubs
     */
    public function a_pipe_manager_can_have_many_pipe_hubs()
    {
        $manager = PipeManager::create([
            PipeHub::create()
                ->name("Test pipe")
                ->description("This is a test pipe")
                ->addPipe(AddPipeManagerTest::class),
            PipeHub::create()
                ->name("Test pipe 2")
                ->description("This is a test pipe 2")
                ->addPipe(AddPipeManagerTest::class),
            PipeHub::create()
                ->name("Test pipe 3")
                ->description("This is a test pipe 3")
                ->addPipe(AddPipeManagerTest::class)
        ]);

        $this->assertCount(3, $manager->hubs());
    }

    /**
     * @test
     * A pipe manager can send a subject through all hubs
     */
    public function a_pipe_manager_can_send_a_subject_through_all_hubs()
    {
        $subject = (object) [
            'name' => 'Kevin Eaton',
            'email' => 'test@email.com'
        ];

        $envelope = PipeManager::create([
            PipeHub::create()
                ->name("Test pipe")
                ->description("This is a test pipe")
                ->addPipe(AddPipeManagerTest::class),
            PipeHub::create()
                ->name("Test pipe 2")
                ->description("This is a test pipe 2")
                ->addPipe(SecondPipeManagerTest::class),
            PipeHub::create()
                ->name("Test pipe 3")
                ->description("This is a test pipe 3")
                ->addPipe(ThirdPipeManagerTest::class)
        ])
        ->subject($subject)
        ->sendIt();

        $this->assertEquals($envelope->subject()->name, 'Kevin Eaton');
    }

    /**
     * @test
     * It can see if any errors were generated
     */
    public function it_can_see_if_any_errors_were_generated()
    {
        $subject = (object) [
            'name' => 'Kevin Eaton',
            'email' => 'testemail.com'
        ];

        $subject = PipeManager::create([
            PipeHub::create()
                ->name("Test pipe")
                ->description("This is a test pipe")
                ->addPipe(AddPipeManagerTest::class),
            PipeHub::create()
                ->name("Test pipe 2")
                ->description("This is a test pipe 2")
                ->addPipe(SecondPipeManagerTest::class),
            PipeHub::create()
                ->name("Test pipe 3")
                ->description("This is a test pipe 3")
                ->addPipe(ThirdPipeManagerTest::class)
        ])
        ->subject($subject)
        ->sendIt();

        $this->assertTrue($subject->hasErrors());
    
    }

    /**
     * @test
     * It can keep track of which hubs have been executed
     */
    public function it_can_keep_track_of_which_hubs_have_been_executed()
    {
        $subject = (object) [
            'name' => 'Kevin Eaton',
            'email' => 'testemail.com'
        ];

        $subject = PipeManager::create([
            PipeHub::create()
                ->name("Test pipe")
                ->description("This is a test pipe")
                ->addPipe(AddPipeManagerTest::class)
                ->then(function($subject) {
                    $subject->canISetSomeOtherStuff = true;
                }),
            PipeHub::create()
                ->name("Test pipe 2")
                ->description("This is a test pipe 2")
                ->addPipe(SecondPipeManagerTest::class),
            PipeHub::create()
                ->name("Test pipe 3")
                ->description("This is a test pipe 3")
                ->addPipe(ThirdPipeManagerTest::class)
        ])
        ->name("Pipe manager name")
        ->description("A full pipe manager description would go here")
        ->subject($subject)
        ->sendIt();

        dd($subject);
    
    }
}

class ThirdPipeManagerTest extends PipeRule
{
    public static $runFunction = 'thirdPipeTest';
    public function thirdPipeTest()
    {
        $this->subject->name = "New Kevin Eaton";
        return "WE DID IT!";
    }
}

class SecondPipeManagerTest extends PipeRule
{
    public static $runFunction = 'secondPipeTest';
    public function secondPipeTest()
    {
        $this->subject->array = [
            'name' => 'Kevin Eaton',
            'email' => 'whatever@kevin.com'
        ];

        return "We did it";
    }
}

class AddPipeManagerTest extends PipeRule
{
    public static $runFunction = 'addingPipeTest';
    public function addingPipeTest()
    {
        $this->error("An error goes here");
    }
}