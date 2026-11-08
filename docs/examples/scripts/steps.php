<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$flow = PipeManager::create([
    PipeHub::create()->name('Prepare')->addPipes([TidyTitle::class, CountWords::class, CheckTitleLength::class]),
    PipeHub::create()->name('Publish')->addPipes([SlugifyTitle::class]),
])->name('Publish a post');

$envelope = $flow->subject((object) ['title' => 'Launch'])->sendIt();

echo $envelope->steps()
    ->map(fn ($step) => $step->wasSkipped()
        ? "↷ {$step->name()} skipped — {$step->skipReason()}"
        : sprintf('✓ %s (%.2f ms)', $step->name(), $step->duration()))
    ->implode("\n");
