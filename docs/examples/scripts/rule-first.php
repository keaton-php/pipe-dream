<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeHub::create()
    ->addPipe(TidyTitle::class)
    ->sendIt($post);

echo $post->title, PHP_EOL;
echo $envelope->logger()->infos()->firstWhere('key', TidyTitle::class)['data'];
