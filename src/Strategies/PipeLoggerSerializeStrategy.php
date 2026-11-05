<?php

namespace R1kon\PipeDream\Strategies;

use R1kon\PipeDream\Contracts\PipeLoggerStrategy;

class PipeLoggerSerializeStrategy implements PipeLoggerStrategy
{
    public function output($records)
    {
        return serialize($records);
    }
    public function rebuild($records)
    {
        return unserialize($records);
    }
}