<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeHub::create()
    ->addPipes([
        fn ($post) => $post->title = trim($post->title),
        fn ($post) => $post->title = preg_replace('/\s+/', '', $post->title), // oops: '' instead of ' '
        fn ($post) => $post->title = ucwords(strtolower($post->title)),
    ])
    ->sendIt($post);

$culprit = $envelope->steps()->first(fn ($step) => ! str_contains($step->returnValue(), ' '));

echo "The spaces vanish at {$culprit->name()}: {$culprit->returnValue()}";
