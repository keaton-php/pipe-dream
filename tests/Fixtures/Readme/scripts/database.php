<?php

use Keaton\PipeDream\Models\PipeDreamLog;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;
use Keaton\PipeDream\Tests\Fixtures\Readme\CheckCampaignStatus;
use Keaton\PipeDream\Tests\Fixtures\Readme\ChooseLandingPage;
use Keaton\PipeDream\Tests\Fixtures\Readme\ResolveRegion;

// README:start
$click = (object) ['id' => 'clk_133', 'campaign' => 'spring-sale', 'country' => 'CA'];

$envelope = PipeEnvelope::wrap($click)->persistWith(new DatabasePersistStrategy());

PipeManager::create([
    PipeHub::create()->name('Validate')->addPipes([CheckCampaignStatus::class]),
    PipeHub::create()->name('Route')->addPipes([ResolveRegion::class, ChooseLandingPage::class]),
])->name('Ad click intake')->subject($envelope)->sendIt();

$envelope->persist();                     // saving again updates the same row

PipeDreamLog::where('run_id', $envelope->runId())->count();   // 1
// README:end
