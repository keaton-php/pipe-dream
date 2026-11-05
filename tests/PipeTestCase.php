<?php

namespace Tests;

use R1kon\PipeDream\PipeHub;

class PipeTestCase
{
    public $subject;
    public $pipeHub;
    
    public function __construct()
    {
        $this->createTestCase();
    }

    public function createTestCase()
    {
        $this->subject = (object) [
            'name' => 'Kevin Eaton',
            'email' => 'kevin@eaton.com',
            'password' => 'pass1234'
        ];

        $this->pipeHub = PipeHub::create()
            ->name("Test pipe")
            ->description("This is a test pipe")
            ->subject($this->subject);

        return [ $this->subject, $this->pipeHub ];
    }

    public function reset()
    {
        $this->createTestCase();
    }
}