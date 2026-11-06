<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\Tests\Fixtures\Readme\CheckAdvertiserSuppressionList;

// README:start
$click = (object) ['id' => 'clk_125', 'campaignType' => 'newsletter', 'email' => 'opted-out@example.test'];

$envelope = PipeHub::create()
    ->name('Suppression')
    ->addPipes([CheckAdvertiserSuppressionList::class])
    ->skipIf(fn ($click) => $click->campaignType === 'newsletter')
    ->sendIt($click);

$envelope->get(CheckAdvertiserSuppressionList::class);   // 4 (PipeRule::SKIP)
$envelope->hasFatalErrors();                             // false
// README:end
