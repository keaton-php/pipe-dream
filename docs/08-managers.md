<!-- nav-group: Basic usage -->
# Stacking hubs with a manager

How do I run several hubs as one flow with one log?

## Stack hubs

A `PipeManager` runs several hubs in order, on the same envelope: one run, one log.

<!-- example: docs/examples/scripts/stack.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeManager::create([
    PipeHub::create()
        ->name('Tidy')
        ->addPipes([
            fn ($post) => $post->title = ucwords(strtolower(trim($post->title))),
            fn ($post) => $post->title = preg_replace('/\s+/', ' ', $post->title),
        ]),

    PipeHub::create()
        ->name('Slug')
        ->addPipes([
            fn ($post) => $post->slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($post->title)), '-'),
        ]),
])
    ->subject($post)
    ->sendIt();

echo $post->slug, PHP_EOL;
echo $envelope->results()->count(), ' results in one log of ', count($envelope->logs()), ' records', PHP_EOL;
```

<!-- output: docs/examples/scripts/stack.php -->
```text
hello-pipe-dream
3 results in one log of 15 records
```

## Name, describe and finish a flow

Name each hub, describe it, fill it with rules, stack the hubs in a manager, and send the post through. Closures and rules mix freely. `skipIf()` skips a hub's rules when it doesn't apply (each is logged as skipped), and `then()` runs after a hub's last rule:

<!-- example: docs/examples/scripts/together.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$post = (object) ['title' => '  hello,   PIPE dream  ', 'status' => 'ready'];

$envelope = PipeManager::create([
    PipeHub::create()
        ->name('Tidy')
        ->description('Make the title presentable')
        ->addPipes([TidyTitle::class]),

    PipeHub::create()
        ->name('Check')
        ->description('Is the title any good?')
        ->addPipes([CountWords::class, CheckTitleLength::class])
        ->then(fn ($post) => $post->readingTime = ceil($post->get('words') / 200).' min'),

    PipeHub::create()
        ->name('Publish')
        ->description('Give it a slug and publish it, unless it is a draft')
        ->skipIf(fn ($post) => $post->status === 'draft')
        ->addPipes([
            SlugifyTitle::class,
            fn ($post) => $post->status = 'published',
        ]),
])
    ->name('Publish a post')
    ->subject($post)
    ->sendIt();

$envelope->steps()
    ->groupBy->hub()
    ->each(function ($steps, $hub) {
        echo $hub, PHP_EOL;

        $steps->each(fn ($step) => printf("  %s: %s\n", $step->name(), $step->status()));
    });

echo $post->status, ', ', $post->readingTime, ' read', PHP_EOL;
```

<!-- output: docs/examples/scripts/together.php -->
```text
Tidy
  TidyTitle: ok
Check
  CountWords: ok
  CheckTitleLength: ok
Publish
  SlugifyTitle: ok
  Closure@together.php:26: ok
published, 1 min read
```

The flow reads like a table of contents, and `steps()` tells the same story: every step, grouped by the hub it ran in. With `'status' => 'draft'`, the Publish hub's steps come back skipped instead, and the post stays a draft ([Skipping steps](09-skipping.md) shows that run).

## Putting it together

A manager has the same shape as a hub: `name()`, `description()`, `then()`, `onError()`, `onFatal()`, `persistLogs()` and `throwFatals()`. Its `then()` runs once, after the last hub. Its `subject()` takes the thing the flow is about, and `sendIt()` runs every hub and returns one envelope. The manager saves the log once, when the flow ends, however it ended.

Next: [Skipping steps](09-skipping.md).
