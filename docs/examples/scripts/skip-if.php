<?php

use Keaton\PipeDream\PipeHub;

$click = (object) ['id' => 'clk_125', 'campaignType' => 'newsletter', 'email' => 'opted-out@example.test'];

$envelope = PipeHub::create()
    ->name('Suppression')
    ->addPipes([CheckAdvertiserSuppressionList::class])
    ->skipIf(fn ($click) => $click->campaignType === 'newsletter')
    ->sendIt($click);

$envelope->get(CheckAdvertiserSuppressionList::class);   // 4 (PipeRule::SKIP)
$envelope->hasFatalErrors();                             // false
