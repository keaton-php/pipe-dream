<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => 'draft: Hello, Pipe Dream'];

$envelope = PipeHub::create()
    ->addPipes([
        function ($post) {
            $post->set('state', str_starts_with($post->title, 'draft:') ? 'draft' : 'ready');
        },
        function ($post) {
            if ($post->get('state') === 'draft') {
                $post->title = trim(substr($post->title, 6));
                $post->set('state', 'in review');
            }
        },
    ])
    ->sendIt($post);

echo $envelope->get('state'), ': ', $post->title;
