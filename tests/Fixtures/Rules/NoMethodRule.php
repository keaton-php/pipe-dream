<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

class NoMethodRule extends PipeRule
{
    public function somethingElse($subject)
    {
        Journal::record("never");
    }
}
