<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class ErrorRule extends PipeRule
{
    public function errorRule($subject)
    {
        Journal::record("ErrorRule");

        $this->error("Something is off");
    }
}
