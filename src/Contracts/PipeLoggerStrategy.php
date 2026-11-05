<?php

namespace R1kon\PipeDream\Contracts;

interface PipeLoggerStrategy
{
    public function output($records);
    public function rebuild($records);
}

?>