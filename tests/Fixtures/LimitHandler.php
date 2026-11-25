<?php

namespace Keaton\PipeDream\Tests\Fixtures;

use Keaton\PipeDream\Exceptions\PipeExceptionHandler;
use Keaton\PipeDream\PipeEnvelope;

class LimitHandler implements PipeExceptionHandler
{
    public static function handle(PipeEnvelope $subject, $exception)
    {
        Journal::record('handled '.get_class($exception));
    }
}
