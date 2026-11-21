<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

class ErrorRule extends PipeRule
{
    public function errorRule($subject)
    {
        Journal::record("ErrorRule");

        $this->error("Something is off");
    }
}
