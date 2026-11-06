<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$post = (object) ['title' => '  hello,   PIPE dream  ', 'status' => 'ready'];

$envelope = PipeManager::create([
    PipeHub::create()
        ->name('Tidy')
        ->description('Make the title presentable')
        ->addPipes([TidyTitle::class]),

    PipeHub::create()
        ->name('Check')
        ->description('Is the title any good?')
        ->addPipes([CountWords::class, CheckTitleLength::class])
        ->then(fn ($post) => $post->readingTime = ceil($post->get('words') / 200).' min'),

    PipeHub::create()
        ->name('Publish')
        ->description('Give it a slug and publish it, unless it is a draft')
        ->skipIf(fn ($post) => $post->status === 'draft')
        ->addPipes([
            SlugifyTitle::class,
            fn ($post) => $post->status = 'published',
        ]),
])
    ->name('Publish a post')
    ->subject($post)
    ->sendIt();

foreach ($envelope->logs() as $record) {
    if ($record['data'] === 'Sending subject through all pipes') {
        echo $record['context']['name'], PHP_EOL;
    } elseif ($record['type'] === 'returnValue') {
        echo '  ', $record['key'], ': ', $record['data'], PHP_EOL;
    }
}

echo $post->status, ', ', $post->readingTime, ' read', PHP_EOL;
