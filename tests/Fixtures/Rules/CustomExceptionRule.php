<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

use Keaton\PipeDream\Tests\Fixtures\QuotaExceeded;

class CustomExceptionRule extends PipeRule
{
    public function customExceptionRule($subject)
    {
        throw new QuotaExceeded("Daily quota reached");
    }
}
