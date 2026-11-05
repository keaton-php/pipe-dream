<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class ErrorsAreFatalRule extends PipeRule
{
    protected $errorsAreFatal = true;

    public function errorsAreFatalRule($subject)
    {
        Journal::record("ErrorsAreFatalRule");

        $this->error("An error this rule treats as fatal");
    }
}
