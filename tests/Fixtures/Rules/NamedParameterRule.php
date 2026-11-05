<?php

namespace Keaton\PipeDream\Tests\Fixtures\Rules;

use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Journal;
use Keaton\PipeDream\Tests\Fixtures\Thermometer;

class NamedParameterRule extends PipeRule
{
    public function namedParameterRule(PipeEnvelope $click, Thermometer $thermometer)
    {
        Journal::record('click '.$click->subject()->id.' at '.$thermometer->read());

        return self::SUCCESS;
    }
}
