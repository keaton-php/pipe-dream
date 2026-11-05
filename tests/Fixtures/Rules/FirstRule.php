<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

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
