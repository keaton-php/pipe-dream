<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

class TypeErrorRule extends PipeRule
{
    public function typeErrorRule($subject)
    {
        Journal::record('TypeErrorRule');

        return $this->needsAnInteger('not a number');
    }

    private function needsAnInteger(int $value): int
    {
        return $value;
    }
}
