<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class SkipsItselfRule extends PipeRule
{
    public function skipsItselfRule($subject)
    {
        Journal::record("SkipsItselfRule");

        return $this->skip("Nothing to check here");
    }
}
