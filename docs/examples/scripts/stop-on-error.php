<?php

use Keaton\PipeDream\PipeHub;

$click = (object) ['id' => 'clk_129', 'campaign' => 'winter-sale', 'destination' => null];

$envelope = PipeHub::create()
    ->name('Validate')
    ->addPipes([CheckCampaignStatus::class, ChooseLandingPage::class])
    ->stopOnError()
    ->sendIt($click);

$envelope->get(CheckCampaignStatus::class);   // 2 (PipeRule::FAIL)
$click->destination;                          // null
