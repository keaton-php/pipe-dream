<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\Tests\Fixtures\Readme\ChooseLandingPage;
use Keaton\PipeDream\Tests\Fixtures\Readme\ResolveRegion;

// README:start
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
// README:end
