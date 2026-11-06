<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Tests\Fixtures\Readme\ChooseLandingPage;
use Keaton\PipeDream\Tests\Fixtures\Readme\RecordBudgetSpend;

// README:start
$click = (object) ['id' => 'clk_128', 'campaign' => 'spring-sale', 'needsReview' => false, 'destination' => null];

$envelope = PipeManager::create([
    PipeHub::create()
        ->name('Fulfillment')
        ->addPipes([RecordBudgetSpend::class])
        ->onFailure(function ($click, $exception) {
            $click->needsReview = true;
        })
        ->failureIsFatal(),

    PipeHub::create()
        ->name('Route')
        ->addPipes([ChooseLandingPage::class]),
])->subject($click)->sendIt();

$click->needsReview;                      // true
$envelope->hasFatalErrors();              // true
$click->destination;                      // null
// README:end
