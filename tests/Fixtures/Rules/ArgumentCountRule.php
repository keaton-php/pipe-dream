<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

class ArgumentCountRule extends PipeRule
{
    public function argumentCountRule($subject)
    {
        Journal::record('ArgumentCountRule');

        $twoArguments = fn ($first, $second) => $first.$second;

        return $twoArguments('only one');
    }
}
