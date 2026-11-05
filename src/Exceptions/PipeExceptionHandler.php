<?php

namespace R1kon\PipeDream\Exceptions;

use R1kon\PipeDream\PipeEnvelope;

interface PipeExceptionHandler
{
    public static function handle(PipeEnvelope $subject, $exception);
}