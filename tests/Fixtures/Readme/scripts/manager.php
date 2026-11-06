<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Tests\Fixtures\Readme\CheckCampaignStatus;
use Keaton\PipeDream\Tests\Fixtures\Readme\ChooseLandingPage;
use Keaton\PipeDream\Tests\Fixtures\Readme\ResolveRegion;

// README:start
$click = (object) ['id' => 'clk_124', 'campaign' => 'spring-sale', 'country' => 'US'];

$envelope = PipeManager::create([
    PipeHub::create()
        ->name('Validate')
        ->description('Is this a click we can buy?')
        ->addPipes([CheckCampaignStatus::class]),

    PipeHub::create()
        ->name('Route')
        ->description('Where does the visitor go?')
        ->addPipes([ResolveRegion::class, ChooseLandingPage::class]),
])
    ->name('Ad click intake')
    ->subject($click)
    ->sendIt();

$envelope->flowName();                    // 'Ad click intake'
$envelope->results()->count();            // 3
$click->destination;                      // 'https://example.test/spring-sale'
// README:end
