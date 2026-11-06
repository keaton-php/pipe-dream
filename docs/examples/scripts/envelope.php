<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeHub::create()
    ->addPipes([
        fn ($post) => $post->title = trim($post->title),
    ])
    ->sendIt($post);

echo get_class($envelope), PHP_EOL;
echo $envelope->title, PHP_EOL;                         // reads go straight to your object
echo $envelope->subject() === $post ? 'the same $post' : 'a copy', PHP_EOL;
