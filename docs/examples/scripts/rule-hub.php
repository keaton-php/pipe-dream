<?php

use Keaton\PipeDream\PipeHub;

$click = (object) ['id' => 'clk_123', 'campaign' => 'spring-sale', 'country' => 'CA'];

$envelope = PipeHub::create()
    ->name('Check the click')
    ->addPipes([
        ResolveRegion::class,
        ChooseLandingPage::class,
    ])
    ->sendIt($click);

$envelope->get(ResolveRegion::class);     // 1 (PipeRule::SUCCESS)
$envelope->get('region');                 // 'North America'
$click->destination;                      // 'https://example.test/spring-sale'
