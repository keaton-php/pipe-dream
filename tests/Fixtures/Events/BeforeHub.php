<?php

namespace Keaton\PipeDream\Tests\Fixtures\Events;

use Keaton\PipeDream\PipeEnvelope;

class BeforeHub
{
    public function __construct(public PipeEnvelope $envelope, public ?string $pipe = null)
    {
    }
}
