<!-- nav-group: Basic usage -->
# Reading the log

How do I ask a run what happened?

Every run keeps a log on its envelope. The quickest way in is `$envelope->steps()`: the run as a list of steps, one per rule (or closure) that ran or was skipped, in order. Underneath it is `$envelope->logs()`, the raw records. Reach for steps first.

## Print the receipt

`echo $envelope->receipt();` prints the whole run, hub by hub and step by step, with what each rule decided and why. A receipt is the data; a format decides how it looks: `$envelope->receipt()->format(new MarkdownFormat)` for a ticket, `new ArrayFormat` for an API, `new ConsoleFormat(colors: true)` for a terminal (the default). Write your own by implementing `Keaton\PipeDream\Contracts\ReceiptFormat` (one method, `format(Receipt $receipt)`), and bind it to that interface to make it the default. A subject that implements `ReceiptSummary` puts its own words in the header ("On hold: two fraud signals"). A saved run prints the same receipt: `PipeDreamLog::forRun($runId)->first()->receipt()`.

## The steps of a run

`steps()` returns a `Collection` of `StepRecord` objects, so you can map, filter and reduce them like any other collection:

<!-- example: docs/examples/scripts/steps.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$flow = PipeManager::create([
    PipeHub::create()->name('Prepare')->addPipes([TidyTitle::class, CountWords::class, CheckTitleLength::class]),
    PipeHub::create()->name('Publish')->addPipes([SlugifyTitle::class]),
])->name('Publish a post');

$envelope = $flow->subject((object) ['title' => 'Launch'])->sendIt();

echo $envelope->steps()
    ->map(fn ($step) => $step->wasSkipped()
        ? "↷ {$step->name()} skipped — {$step->skipReason()}"
        : sprintf('✓ %s (%.2f ms)', $step->name(), $step->duration()))
    ->implode("\n");
```

<!-- output: docs/examples/scripts/steps.php -->
```text
✓ TidyTitle (0.38 ms)
✓ CountWords (0.02 ms)
↷ CheckTitleLength skipped — A one-word title is fine
✓ SlugifyTitle (0.05 ms)
```

A step is computed from the log, not stored, so it reads the same live or rebuilt from the database. Hub-level lines (a hub's own "Sending subject through all pipes") are not steps and never appear in the list.

## What a step tells you

Each `StepRecord` answers the questions you actually have about a step:

| Method | What it gives you |
|---|---|
| `name()` | A short name: the rule's class basename, or a closure's `Closure@file:line`. |
| `rule()` | The full key it logged under (a rule's FQCN, or the closure label). |
| `hub()` | The hub it ran in, when the flow named its hubs. |
| `duration()` | How long it took, in milliseconds (null with `LEVEL_NO_TIMERS`). |
| `returnValue()` | What it returned: a result constant, or a custom value. |
| `status()` | `'ok'`, `'skipped'`, `'error'` or `'fatal'`. |
| `wasOk()`, `wasSkipped()`, `hasErrors()`, `wasFatal()` | The same, as yes/no questions. |
| `skipReason()` | Why a skipped step was skipped. |
| `messages()` | The info, warning and error lines the step logged, as a `Collection`. |

## Find the failing step

When the answer is wrong, read down the steps for the first one whose output went bad. Here the second step eats every space:

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

$culprit = $envelope->steps()->first(fn ($step) => ! str_contains($step->returnValue(), ' '));

echo "The spaces vanish at {$culprit->name()}: {$culprit->returnValue()}";
```

<!-- output: docs/examples/scripts/hub-log-broken.php -->
```text
The spaces vanish at Closure@hub-log-broken.php:10: hello,PIPEdream
```

`first()` reads down the run in order and hands you the first step whose output lost its spaces: the second closure.

## The lower-level view: the records

`steps()` is built from the log. When you want every record as it was written — including the hub's own lines — read `$envelope->logs()`:

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

## What a record holds

Every record is an array with a `time`, a `key` (usually the rule class, a closure's file and line, or a `PipeHub:…` / `PipeManager:…` name), a `type` (`info`, `warning`, `error`, `fatal`, `timerStart`, `timerEnd`, `returnValue`), its `data` and a `context` array. A hub's start and skip records carry its name and description in `context`.

## Slices for the common questions

- **The steps:** `$envelope->steps()` is a `Collection<StepRecord>`; filter it with `->firstWhere(...)`, group it with `->groupBy->hub()`.
- **The logger:** `errors()`, `fatals()`, `warnings()`, `infos()`, `timers()`, `returnValues()` and `recordsByKey($key)`.
- **The envelope:** `hasErrors()` and `hasFatalErrors()` answer the most common questions.
- **The results:** `$envelope->results()` has `successes()`, `failures()`, `fatalFailures()` and `skips()`.

Add your own lines with `$envelope->logger()->info($key, $message)`.

## Log levels

`$envelope->logLevel(PipeLogger::LEVEL_EVERYTHING)` is the default. `LEVEL_NO_TIMERS` drops the rule timers (steps still list, but `duration()` is null), and `LEVEL_PROBLEMS` keeps only warnings, errors and fatals (so there are no step result records to list). Records below the level are never written. `$envelope->results()` is recorded whatever the level.

Next: [Writing rules](06-rules.md).
