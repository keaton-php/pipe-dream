<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

class SkipAllRule extends PipeRule
{
    public function skipAllRule($subject)
    {
        Journal::record("SkipAllRule");
        $subject->skipAll();

        return self::SUCCESS;
    }
}
