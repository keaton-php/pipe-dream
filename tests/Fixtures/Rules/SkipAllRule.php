<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class SkipAllRule extends PipeRule
{
    public function skipAllRule($subject)
    {
        Journal::record("SkipAllRule");
        $subject->skipAll();

        return self::SUCCESS;
    }
}
