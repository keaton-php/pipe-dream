<?php

namespace R1kon\PipeDream\Exceptions;

use R1kon\PipeDream\PipeEnvelope;

/**
 * Optional per-exception handlers, registered as an
 * [ExceptionClass => HandlerClass] map under the `pipehub:exceptions`
 * container binding or with PipeEnvelope::addExceptionHandler().
 */
interface PipeExceptionHandler
{
    public static function handle(PipeEnvelope $subject, $exception);
}
