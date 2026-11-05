<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class FirstRule extends PipeRule
{
    public static $name = 'First rule';
    public static $description = 'Records that it ran';

    public function firstRule($subject)
    {
        Journal::record('FirstRule');

        return self::SUCCESS;
    }
}
