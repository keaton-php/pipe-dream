<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$envelope = PipeManager::create([

    PipeHub::create()
        ->name('Accept the click')
        ->description('Is this a real click we can trace back to an ad?')
        ->addPipes([
            RequireClickId::class,
            ParseUserAgent::class,
            ResolveGeoIp::class,
        ]),                           // no click id, no click: the flow ends here

    PipeHub::create()
        ->name('Filter the junk')
        ->description('Does this look like a person, or a script?')
        ->addPipes([
            IsKnownBot::class,
            DatacenterIpCheck::class,
            HeadlessBrowserSignals::class,
            RateLimitPerFingerprint::class,
        ])
        ->skipIf(fn ($click) => $click->fromAllowlistedPartner(), 'Allowlisted partner')
        ->then(fn ($click) => $click->botSignals() >= 3 ? $click->drop("{$click->botSignals()} automation signals") : null),

    PipeHub::create()
        ->name('Attribute')
        ->description('Which campaign gets the credit?')
        ->addPipes([
            MatchToCampaign::class,
            DeduplicateWithinWindow::class,
            AssignLastTouch::class,
        ])
        ->skipIf(fn ($click) => $click->isDropped() ? "Dropped: {$click->dropReason}" : null),

    PipeHub::create()
        ->name('Record & notify')
        ->description('Write the row, update the counters, tell the advertiser')
        ->addPipes([
            WriteClickRow::class,
            UpdateCampaignCounters::class,
            FirePostback::class,
        ])
        ->skipIf(fn ($click) => $click->isDropped() ? "Dropped: {$click->dropReason}" : null),

])
    ->name('Click intake')
    ->subject($click)
    ->then(fn ($click) => $click->settle())
    ->sendIt();
