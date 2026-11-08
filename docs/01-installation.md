<!-- nav-group: Start here -->
# Requirements and installation

How do I install Pipe Dream and check that it works?

## Requirements

Pipe Dream needs PHP 8.1 or newer and Laravel 9 to 13. The [compatibility table](23-compatibility.md) lists the combinations the test suite covers.

## Install

```bash
composer require keaton-php/pipe-dream
```

The service provider is discovered automatically. Nothing else is required to run a flow.

Saving logs to the database is optional. If you want it, publish and run the migration (see [Saving logs to the database](13-persisting.md)):

```bash
php artisan vendor:publish --tag=pipe-dream-migrations
php artisan migrate
```

## Check that it works

Run this in `php artisan tinker`, a route or a command:

<!-- example: docs/examples/scripts/hub.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => '  hello,   PIPE dream  '];

PipeHub::create()
    ->addPipes([
        fn ($post) => $post->title = trim($post->title),
        fn ($post) => $post->title = preg_replace('/\s+/', ' ', $post->title),
        fn ($post) => $post->title = ucwords(strtolower($post->title)),
    ])
    ->sendIt($post);

echo $post->title;
```

It prints:

<!-- output: docs/examples/scripts/hub.php -->
```text
Hello, Pipe Dream
```

If you see `Hello, Pipe Dream`, the package is installed and the hub ran your three steps.

## What you just installed

- `PipeHub`, `PipeManager`, `PipeEnvelope` and `PipeRule`: the classes you build flows with.
- A service provider (auto-discovered) that publishes the `pipe_dream_logs` migration under the `pipe-dream-migrations` tag.
- `DatabasePersistStrategy` and the `PipeDreamLog` model, which you use only if you save logs.

Next: [Your first flow](02-quickstart.md).
