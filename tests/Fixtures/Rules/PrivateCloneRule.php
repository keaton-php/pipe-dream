<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

/** A valid rule that refuses to be copied. */
class PrivateCloneRule extends PipeRule
{
    public function privateCloneRule($subject)
    {
        Journal::record('PrivateCloneRule');

        return self::SUCCESS;
    }

    private function __clone()
    {
    }
}
