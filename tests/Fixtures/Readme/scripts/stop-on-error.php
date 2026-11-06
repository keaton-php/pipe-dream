<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\Tests\Fixtures\Readme\CheckCampaignStatus;
use Keaton\PipeDream\Tests\Fixtures\Readme\ChooseLandingPage;

// README:start
$click = (object) ['id' => 'clk_129', 'campaign' => 'winter-sale', 'destination' => null];

$envelope = PipeHub::create()
    ->name('Validate')
    ->addPipes([CheckCampaignStatus::class, ChooseLandingPage::class])
    ->stopOnError()
    ->sendIt($click);

$envelope->get(CheckCampaignStatus::class);   // 2 (PipeRule::FAIL)
$click->destination;                          // null
// README:end
