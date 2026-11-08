<!-- nav-group: Basic usage -->
# The envelope

What does a pipe actually receive, and how do pipes share values?

## What the envelope is

The hub wraps your subject in a `PipeEnvelope`. That is what each pipe receives as its first parameter, and what `sendIt()` returns. Leave that parameter untyped, or type it `PipeEnvelope`.

## Reading and writing through it

Reads, writes and method calls go straight through to your object:

<!-- example: docs/examples/scripts/envelope.php -->
```php
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
```

<!-- output: docs/examples/scripts/envelope.php -->
```text
Keaton\PipeDream\PipeEnvelope
hello,   PIPE dream
the same $post
```

One limit: a property read through the envelope is a copy, so `$envelope->tags[] = 'new'` changes nothing on your object. Read the array, change it, and assign the whole property back: `$envelope->tags = [...$envelope->tags, 'new'];`.

## Sharing values between pipes

The envelope also holds values of its own, for the pipes to pass along: `set()` one in a pipe, `get()` it in a later one. That makes small state machines easy:

<!-- example: docs/examples/scripts/state.php -->
```php
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
```

<!-- output: docs/examples/scripts/state.php -->
```text
in review: Hello, Pipe Dream
```

Rule results are stored the same way, under the rule's class name: `$envelope->get(TidyTitle::class)`. Every envelope also has a `runId()`, a uuid that identifies the run.

Next: [Reading the log](05-reading-the-log.md).
