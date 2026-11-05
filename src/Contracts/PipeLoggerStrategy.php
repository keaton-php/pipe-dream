<?php

namespace R1kon\PipeDream\Contracts;

/**
 * Turns log records into something storable (a string) and back.
 */
interface PipeLoggerStrategy
{
    public function output($records);

    public function rebuild($records);
}
