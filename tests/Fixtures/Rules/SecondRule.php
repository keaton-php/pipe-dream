<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

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
