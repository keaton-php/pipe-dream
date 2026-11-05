<?php

namespace R1kon\PipeDream\Tests\Fixtures;

use R1kon\PipeDream\Contracts\PipeLoggerPersistStrategy;
use R1kon\PipeDream\PipeEnvelope;

class CountingPersist implements PipeLoggerPersistStrategy
{
    public int $calls = 0;

    public array $recordCounts = [];

    public function persist(PipeEnvelope $subject)
    {
        $this->calls++;
        $this->recordCounts[] = count($subject->logs());

        return $this->calls;
    }
}
