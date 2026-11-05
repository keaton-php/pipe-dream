<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class NoMethodRule extends PipeRule
{
    public function somethingElse($subject)
    {
        Journal::record("never");
    }
}
