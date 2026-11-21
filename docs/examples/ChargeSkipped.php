<?php

use Keaton\PipeDream\PipeEnvelope;

class ChargeSkipped
{
    public function __construct(public PipeEnvelope $order) {}
}
