<?php

namespace Keaton\PipeDream\Exceptions;

use Keaton\PipeDream\PipeEnvelope;

/**
 * Optional per-exception handlers, registered as an
 * [ExceptionClass => HandlerClass] map under the `pipehub:exceptions`
 * container binding or with PipeEnvelope::addExceptionHandler().
 */
interface PipeExceptionHandler
{
    public static function handle(PipeEnvelope $subject, $exception);
}
