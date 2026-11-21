<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

class ErrorsAreFatalRule extends PipeRule
{
    protected $errorsAreFatal = true;

    public function errorsAreFatalRule($subject)
    {
        Journal::record("ErrorsAreFatalRule");

        $this->error("An error this rule treats as fatal");
    }
}
