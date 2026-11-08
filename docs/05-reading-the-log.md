<!-- nav-group: Basic usage -->
# Reading the log

How do I ask a run what happened?

Every run keeps a log on its envelope. `$envelope->logs()` returns the records as arrays, and `$envelope->results()` returns what each step returned.

## Find the failing step

When the answer is wrong, the log tells you which step made it wrong:

1. Print the `returnValue` records, one per step.
2. Read down the list for the first line where the value is wrong.
3. That line's `key` names the step (a rule class, or a closure's file and line).

Here the second step eats every space:

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

The first line is right, the second is where the spaces are gone, so the second closure, `hub-log-broken.php:10`, is the culprit.

## What a record holds

Every record is an array with a `time`, a `key` (usually the rule class, a closure's file and line, or a `PipeHub:…` / `PipeManager:…` name), a `type` (`info`, `warning`, `error`, `fatal`, `timerStart`, `timerEnd`, `returnValue`), its `data` and a `context` array. A hub's start and skip records carry its name and description in `context`.

## Slices for the common questions

- **The logger:** `errors()`, `fatals()`, `warnings()`, `infos()`, `timers()`, `returnValues()` and `recordsByKey($key)`.
- **The envelope:** `hasErrors()` and `hasFatalErrors()` answer the most common questions.
- **The results:** `$envelope->results()` has `successes()`, `failures()`, `fatalFailures()` and `skips()`.

Add your own lines with `$envelope->logger()->info($key, $message)`.

## Log levels

`$envelope->logLevel(PipeLogger::LEVEL_EVERYTHING)` is the default. `LEVEL_NO_TIMERS` drops the rule timers, and `LEVEL_PROBLEMS` keeps only warnings, errors and fatals. Records below the level are never written. Results are recorded whatever the level.

Next: [Writing rules](06-rules.md).
