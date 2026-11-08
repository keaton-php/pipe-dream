<?php

use Keaton\PipeDream\PipeEnvelope;

class PaymentStepFinished
{
    public function __construct(public PipeEnvelope $order, public ?string $rule = null) {}
}
