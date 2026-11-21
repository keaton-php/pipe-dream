<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

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
