<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class RunFunctionRule extends PipeRule
{
    public static $runFunction = "check";

    public function check($subject)
    {
        Journal::record("RunFunctionRule");

        return self::FAIL;
    }
}
