<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;

use R1kon\PipeDream\Tests\Fixtures\Thermometer;

class InjectedRule extends PipeRule
{
    public function injectedRule($subject, Thermometer $thermometer)
    {
        Journal::record("temperature=".$thermometer->read());

        return self::SUCCESS;
    }
}
