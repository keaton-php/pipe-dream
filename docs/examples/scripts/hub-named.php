<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeHub::create()
    ->name('Tidy the title')
    ->description('Make the title presentable')
    ->addPipe(fn ($post) => $post->title = trim($post->title))
    ->addPipes([
        fn ($post) => $post->title = preg_replace('/\s+/', ' ', $post->title),
        fn ($post) => $post->title = ucwords(strtolower($post->title)),
    ])
    ->sendIt($post);

echo $envelope->flowName(), ': ', $post->title;
