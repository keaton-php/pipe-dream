<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeManager::create([
    PipeHub::create()
        ->name('Tidy')
        ->addPipes([
            fn ($post) => $post->title = ucwords(strtolower(trim($post->title))),
            fn ($post) => $post->title = preg_replace('/\s+/', ' ', $post->title),
        ]),

    PipeHub::create()
        ->name('Slug')
        ->addPipes([
            fn ($post) => $post->slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($post->title)), '-'),
        ]),
])
    ->subject($post)
    ->sendIt();

echo $post->slug, PHP_EOL;
echo $envelope->results()->count(), ' results in one log of ', count($envelope->logs()), ' records', PHP_EOL;
