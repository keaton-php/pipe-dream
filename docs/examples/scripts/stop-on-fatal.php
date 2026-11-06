<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$click = (object) ['id' => 'clk_127', 'campaign' => 'no-such-campaign', 'destination' => null];

$envelope = PipeManager::create([
    PipeHub::create()
        ->name('Validate')
        ->addPipes([CheckCampaignStatus::class])
        ->stopOnFatal(),

    PipeHub::create()
        ->name('Route')
        ->addPipes([ChooseLandingPage::class]),
])->subject($click)->sendIt();

$envelope->hasFatalErrors();              // true
$click->destination;                      // null
