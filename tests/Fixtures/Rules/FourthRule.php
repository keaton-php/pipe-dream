<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class FourthRule extends PipeRule
{
    public static $name = 'Fourth rule';
    public static $description = 'Records that it ran';

    public function fourthRule($subject)
    {
        Journal::record('FourthRule');

        return self::SUCCESS;
    }
}
