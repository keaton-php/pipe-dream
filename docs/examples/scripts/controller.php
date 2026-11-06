<?php

$publish = app(PublishPost::class);

$posts = [
    (object) ['id' => 1, 'title' => '  hello,   PIPE dream  ', 'status' => 'ready'],
    (object) ['id' => 2, 'title' => '   ', 'status' => 'ready'],
    (object) ['id' => 3, 'title' => 'Work in progress', 'status' => 'draft'],
];

foreach ($posts as $post) {
    $response = $publish($post);

    echo $post->id, ': ', $response->getStatusCode(), ' ', $response->headers->get('Location') ?? $response->getContent(), PHP_EOL;
}
