<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

class SecondRule extends PipeRule
{
    public static $name = 'Second rule';
    public static $description = 'Records that it ran';

    public function secondRule($subject)
    {
        Journal::record('SecondRule');

        return self::SUCCESS;
    }
}
