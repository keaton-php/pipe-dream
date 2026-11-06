<?php

// README:start
use Keaton\PipeDream\PipeEnvelope;

class BotChecksStarted
{
    public function __construct(public PipeEnvelope $click) {}
}

class BotChecksFinished
{
    public function __construct(public PipeEnvelope $click) {}
}

class BotChecksSkipped
{
    public function __construct(public PipeEnvelope $click) {}
}

class RuleStarting
{
    public function __construct(public PipeEnvelope $click, public ?string $rule = null) {}
}

class RuleFinished
{
    public function __construct(public PipeEnvelope $click, public ?string $rule = null) {}
}
// README:end
