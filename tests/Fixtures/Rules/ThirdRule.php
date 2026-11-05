<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

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
