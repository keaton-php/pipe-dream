<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

class UnhandledTypeErrorRule extends PipeRule
{
    public function unhandledTypeErrorRule($subject)
    {
        Journal::record('UnhandledTypeErrorRule');
        $this->withoutErrorHandling();

        return strlen([]);
    }
}
