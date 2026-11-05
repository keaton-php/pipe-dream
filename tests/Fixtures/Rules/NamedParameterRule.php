<?php

namespace R1kon\PipeDream\Tests\Fixtures\Rules;

use R1kon\PipeDream\PipeEnvelope;
use R1kon\PipeDream\PipeRule;
use R1kon\PipeDream\Tests\Fixtures\Journal;
use R1kon\PipeDream\Tests\Fixtures\Thermometer;

class NamedParameterRule extends PipeRule
{
    public function namedParameterRule(PipeEnvelope $click, Thermometer $thermometer)
    {
        Journal::record('click '.$click->subject()->id.' at '.$thermometer->read());

        return self::SUCCESS;
    }
}
