<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;

use Keaton\PipeDream\Tests\Fixtures\Thermometer;

class InjectedRule extends PipeRule
{
    public function injectedRule($subject, Thermometer $thermometer)
    {
        Journal::record("temperature=".$thermometer->read());

        return self::SUCCESS;
    }
}
