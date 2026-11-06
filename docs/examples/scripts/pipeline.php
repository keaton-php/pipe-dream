<?php

use Illuminate\Pipeline\Pipeline;

$post = (object) ['title' => '  hello,   PIPE dream  '];

app(Pipeline::class)
    ->send($post)
    ->through([
        function ($post, $next) {
            $post->title = trim($post->title);

            return $next($post);
        },
        function ($post, $next) {
            $post->title = preg_replace('/\s+/', ' ', $post->title);

            return $next($post);
        },
        function ($post, $next) {
            $post->title = ucwords(strtolower($post->title));

            return $next($post);
        },
    ])
    ->thenReturn();

echo $post->title;
