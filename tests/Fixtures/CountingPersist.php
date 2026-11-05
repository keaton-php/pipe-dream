<?php

namespace Keaton\PipeDream\Tests\Fixtures;

use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\PipeEnvelope;

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
