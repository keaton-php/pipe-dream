<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

class RunFunctionRule extends PipeRule
{
    public static $runFunction = "check";

    public function check($subject)
    {
        Journal::record("RunFunctionRule");

        return self::FAIL;
    }
}
