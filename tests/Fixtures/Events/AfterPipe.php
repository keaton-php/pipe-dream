<?php

namespace Keaton\PipeDream\Tests\Fixtures\Events;

use Keaton\PipeDream\PipeEnvelope;

class AfterPipe
{
    public function __construct(public PipeEnvelope $envelope, public ?string $pipe = null)
    {
    }
}
