<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$post = (object) ['title' => 'work in progress', 'status' => 'draft'];

$envelope = PipeManager::create([
    PipeHub::create()
        ->name('Tidy')
        ->addPipes([TidyTitle::class]),

    PipeHub::create()
        ->name('Publish')
        ->skipIf(fn ($post) => $post->status === 'draft')
        ->addPipes([SlugifyTitle::class]),
])
    ->subject($post)
    ->sendIt();

foreach ($envelope->results() as $result) {
    echo $result->rule(), ': ', $result->result(), PHP_EOL;
}

echo isset($post->slug) ? 'published' : 'still a draft';
