<?php

namespace R1kon\PipeDream\Tests\Fixtures;

use R1kon\PipeDream\Exceptions\PipeExceptionHandler;
use R1kon\PipeDream\PipeEnvelope;

class LimitHandler implements PipeExceptionHandler
{
    public static function handle(PipeEnvelope $subject, $exception)
    {
        Journal::record('handled '.get_class($exception));
    }
}
