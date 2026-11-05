<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

class FatalRule extends PipeRule
{
    public function fatalRule($subject)
    {
        Journal::record("FatalRule");

        $this->fatal("Cannot continue");
    }
}
