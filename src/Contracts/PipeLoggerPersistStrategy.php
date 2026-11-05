<?php
namespace R1kon\PipeDream\Contracts;

use R1kon\PipeDream\PipeEnvelope;

interface PipeLoggerPersistStrategy
{
    public function persist(PipeEnvelope $subject);
}