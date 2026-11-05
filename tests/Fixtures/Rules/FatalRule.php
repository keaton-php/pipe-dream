<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class FatalRule extends PipeRule
{
    public function fatalRule($subject)
    {
        Journal::record("FatalRule");

        $this->fatal("Cannot continue");
    }
}
