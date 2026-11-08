# Pipe Dream

[![Latest Version on Packagist](https://img.shields.io/packagist/v/keaton-php/pipe-dream.svg?style=flat-square)](https://packagist.org/packages/keaton-php/pipe-dream)
[![Tests](https://img.shields.io/github/actions/workflow/status/keaton-php/pipe-dream/tests.yml?branch=1.x&label=tests&style=flat-square)](https://github.com/keaton-php/pipe-dream/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/packagist/dependency-v/keaton-php/pipe-dream/php?style=flat-square)](https://packagist.org/packages/keaton-php/pipe-dream)
[![Total Downloads](https://img.shields.io/packagist/dt/keaton-php/pipe-dream.svg?style=flat-square)](https://packagist.org/packages/keaton-php/pipe-dream)
[![License](https://img.shields.io/packagist/l/keaton-php/pipe-dream.svg?style=flat-square)](LICENSE)

Pipe Dream turns a Laravel pipeline into a flow you can read, and a log you can read back: every step named, timed and recorded.

## What a pipeline is

A pipeline sends one value (the **subject**) through a list of steps (the **pipes**), in order. Each pipe gets the subject, does its bit, and passes it on. Laravel ships one, `Illuminate\Pipeline\Pipeline`. A `PipeHub` is Pipe Dream's: the same idea, with plain closures as the pipes.

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

<!-- output: docs/examples/scripts/hub.php -->
```text
Hello, Pipe Dream
```

No `$next` to call and nothing to return: the hub moves on to the next pipe by itself.

## Now ask it what happened

Here is the difference. A plain pipeline hands you the final value and nothing else: if the title came out wrong, you are left guessing which step broke it. The hub kept notes. Ask it what happened:

<!-- example: docs/examples/scripts/hub-log.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeHub::create()
    ->addPipes([
        fn ($post) => $post->title = trim($post->title),
        fn ($post) => $post->title = preg_replace('/\s+/', ' ', $post->title),
        fn ($post) => $post->title = ucwords(strtolower($post->title)),
    ])
    ->sendIt($post);

foreach ($envelope->logs() as $record) {
    printf("%-11s %-24s %s\n", $record['type'], $record['key'], $record['data']);
}
```

<!-- output: docs/examples/scripts/hub-log.php -->
```text
info        PipeHub:sendIt           Sending subject through all pipes
timerStart  Closure@hub-log.php:9    Executing rule Closure@hub-log.php:9
returnValue Closure@hub-log.php:9    hello,   PIPE dream
timerEnd    Closure@hub-log.php:9    Finished executing rule Closure@hub-log.php:9
timerStart  Closure@hub-log.php:10   Executing rule Closure@hub-log.php:10
returnValue Closure@hub-log.php:10   hello, PIPE dream
timerEnd    Closure@hub-log.php:10   Finished executing rule Closure@hub-log.php:10
timerStart  Closure@hub-log.php:11   Executing rule Closure@hub-log.php:11
returnValue Closure@hub-log.php:11   Hello, Pipe Dream
timerEnd    Closure@hub-log.php:11   Finished executing rule Closure@hub-log.php:11
info        PipeHub:sendIt           Finalized result!
```

Every pipe was timed, and what each one returned was recorded. You can see exactly where `hello,   PIPE dream` became `Hello, Pipe Dream`, without adding a single line of logging to your code. `$envelope->results()` holds the same results, ready to check in code.

Now break it on purpose. Change the second step so it eats every space, and run the hub again:

<!-- example: docs/examples/scripts/hub-log-broken.php -->
```php
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

foreach ($envelope->logs() as $record) {
    if ($record['type'] === 'returnValue') {
        printf("%-30s %s\n", $record['key'], $record['data']);
    }
}
```

<!-- output: docs/examples/scripts/hub-log-broken.php -->
```text
Closure@hub-log-broken.php:9   hello,   PIPE dream
Closure@hub-log-broken.php:10  hello,PIPEdream
Closure@hub-log-broken.php:11  Hello,pipedream
```

You don't have to guess which step ate the spaces. The log already points at the second one: it's the first line where they're gone.

That's the whole difference. A pipeline tells you the answer. A hub hands you the receipt.

And that's only the start. Once every step is a named, recorded thing, a lot more opens up:

- **Rules as classes:** each pipe becomes a small, testable class with a name, so the log reads like a description of your business.
- **Stacked hubs:** group steps into stages and run the stages as one flow, with one log.
- **Control flow:** skip a step, stop a flow early, redirect, or catch a failure without losing the log.
- **Saved runs:** store every log and query it later: which runs hit problems, and exactly where.
- **A flow you can draw:** read a flow back and render it as a page, then watch real traffic move through it.

All of it builds on what you just saw. The docs pick it up from here.

## Installation

```bash
composer require keaton-php/pipe-dream
```

Pipe Dream needs PHP 8.1 or newer and Laravel 9 to 13. The service provider is discovered automatically.

## Documentation

The full documentation is at [kevineaton.dev/docs/pipe-dream](https://kevineaton.dev/docs/pipe-dream/). Start with [Your first flow](https://kevineaton.dev/docs/pipe-dream/quickstart/): it builds the flow above, step by step, and reads its log.

## Read the story

Why a flow that reads like a table of contents beats a 400-line method: [Pipe-Driven Development](https://kevineaton.dev/writing/pipe-driven-development/).

## License

The MIT License (MIT). See [LICENSE](LICENSE) for the full text, and [CHANGELOG.md](CHANGELOG.md) for what changed.

## Credits

- [Kevin Eaton](https://kevineaton.dev)
- [All contributors](https://github.com/keaton-php/pipe-dream/graphs/contributors)
