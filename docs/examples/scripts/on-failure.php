<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

// The budget service is down, so every charge() throws.
app()->instance(BudgetLedger::class, new BudgetLedger(available: false));

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
