<?php

use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeLogger;

$click = (object) ['id' => 'clk_132', 'campaign' => 'no-such-campaign'];

$envelope = PipeEnvelope::wrap($click)->logLevel(PipeLogger::LEVEL_PROBLEMS);

PipeHub::create()->addPipes([CheckCampaignStatus::class])->sendIt($envelope);

count($envelope->logs());                 // 2 (the rule's fatal, and the hub's stop warning)
$envelope->results()->count();            // 1
