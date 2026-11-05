<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class ThirdRule extends PipeRule
{
    public static $name = 'Third rule';
    public static $description = 'Records that it ran';

    public function thirdRule($subject)
    {
        Journal::record('ThirdRule');

        return self::SUCCESS;
    }
}
