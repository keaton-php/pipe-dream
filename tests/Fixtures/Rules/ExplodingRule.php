<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

class ExplodingRule extends PipeRule
{
    public function explodingRule($subject)
    {
        Journal::record("ExplodingRule");

        throw new \RuntimeException("Boom");
    }
}
