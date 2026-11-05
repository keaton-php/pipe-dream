<?php

namespace R1kon\PipeDream\Tests\Fixtures\Events;

use R1kon\PipeDream\PipeEnvelope;

class HubSkipped
{
    public function __construct(public PipeEnvelope $envelope, public ?string $pipe = null)
    {
    }
}
