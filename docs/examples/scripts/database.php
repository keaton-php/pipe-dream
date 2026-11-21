<?php

use Keaton\PipeDream\Models\PipeDreamLog;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeEnvelope::wrap($post)->persistWith(new DatabasePersistStrategy());

PipeManager::create([
    PipeHub::create()->name('Tidy')->addPipes([TidyTitle::class]),
    PipeHub::create()->name('Slug')->addPipes([SlugifyTitle::class]),
])->name('Publish a post')->subject($envelope)->sendIt();

PipeDreamLog::where('run_id', $envelope->runId())->count();   // 1
