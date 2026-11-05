<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

class UnhandledExplodingRule extends PipeRule
{
    public function unhandledExplodingRule($subject)
    {
        Journal::record("UnhandledExplodingRule");
        $this->withoutErrorHandling();

        throw new \RuntimeException("Boom, unhandled");
    }
}
