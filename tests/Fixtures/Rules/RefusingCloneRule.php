<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;

/** A rule whose public __clone() rejects the copy. */
class RefusingCloneRule extends PipeRule
{
    public function __clone()
    {
        throw new \LogicException('This rule holds a connection and must not be copied');
    }

    public function refusingCloneRule($subject)
    {
        return self::SUCCESS;
    }
}
