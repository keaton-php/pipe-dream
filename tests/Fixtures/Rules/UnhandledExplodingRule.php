<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class UnhandledExplodingRule extends PipeRule
{
    public function unhandledExplodingRule($subject)
    {
        Journal::record("UnhandledExplodingRule");
        $this->withoutErrorHandling();

        throw new \RuntimeException("Boom, unhandled");
    }
}
