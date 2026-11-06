<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeHub::create()
    ->addPipes([
        TidyTitle::class,
        CountWords::class,
        CheckTitleLength::class,
        SlugifyTitle::class,
    ])
    ->sendIt($post);

echo $post->slug, ' (', $envelope->get('words'), ' words)', PHP_EOL;

foreach ($envelope->results() as $result) {
    echo $result->rule(), ': ', $result->result(), PHP_EOL;
}
