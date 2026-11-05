<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class ExplodingRule extends PipeRule
{
    public function explodingRule($subject)
    {
        Journal::record("ExplodingRule");

        throw new \RuntimeException("Boom");
    }
}
