<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

use R1kon\PipeDream\Tests\Fixtures\QuotaExceeded;

class CustomExceptionRule extends PipeRule
{
    public function customExceptionRule($subject)
    {
        throw new QuotaExceeded("Daily quota reached");
    }
}
