<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

class SkipsItselfRule extends PipeRule
{
    public function skipsItselfRule($subject)
    {
        Journal::record("SkipsItselfRule");

        return $this->skip("Nothing to check here");
    }
}
