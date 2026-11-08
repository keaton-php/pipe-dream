<!-- nav-group: Keeping and reading logs -->
# Saving logs to the database

How do I store every run, so I can ask later which ones hit problems?

Saving is how a log outlives the request: you can find the runs that failed, and exactly where, long after they happened.

## Set up the table

Publish and run the migration:

```bash
php artisan vendor:publish --tag=pipe-dream-migrations
php artisan migrate
```

## Save a run

Give the envelope a `DatabasePersistStrategy`. Wrap the subject yourself with `PipeEnvelope::wrap()` to set things like this before the run:

<!-- example: docs/examples/scripts/database.php -->
```php
<?php

use Keaton\PipeDream\Models\PipeDreamLog;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;

$post = (object) ['title' => '  hello,   PIPE dream  '];

$envelope = PipeEnvelope::wrap($post)->persistWith(new DatabasePersistStrategy());

PipeManager::create([
    PipeHub::create()->name('Tidy')->addPipes([TidyTitle::class]),
    PipeHub::create()->name('Slug')->addPipes([SlugifyTitle::class]),
])->name('Publish a post')->subject($envelope)->sendIt();

PipeDreamLog::where('run_id', $envelope->runId())->count();   // 1
```

A manager saves once, when the flow ends, however it ended: one row per run, in `pipe_dream_logs`. Saving the same run again updates its row. A hub run on its own saves when it finishes.

## What is saved

Once a persistence strategy is set up, a manager saves the log once, when the flow ends, whether it completed, stopped early or threw. `->persistLogs(false)` turns that off, so you can call `$envelope->persist()` yourself.

The `pipe_dream_logs` row holds the run id, the flow's name, the log (JSON), `has_errors` / `has_fatals`, and the subject's type and id when the subject is a saved Eloquent model. It is written with a single upsert keyed on the run id.

## Which strategy runs

A run is saved with the strategy given to `$envelope->persistWith()`, else the one bound to `PipeLoggerPersistStrategy` in the container (bind `DatabasePersistStrategy` there once to use it for every flow); with neither, nothing is written. [Choosing a persist strategy](15-persist-strategies.md) compares the options.

## JSON-safe log values

Every value is made JSON-safe as it is recorded, so a value a rule returned can never make the log fail to encode and hide what the flow did. (Saving can still fail for ordinary reasons such as the database being down; those errors are not swallowed.)

- A rule may return anything, but `NAN` and `INF` are logged as the strings `"NAN"` / `"INF"`, and resources as `"resource(stream)"`.
- Closures are logged as `"Closure"`, backed enums as their value and unit (pure) enums as their name, dates as ISO 8601 strings, and exceptions as their class and message.
- Other objects are logged as their public properties plus a `__class` key (or what their `jsonSerialize()`, `toArray()` or `__toString()` gives). Cycles are logged as `"[recursion: Class]"`, nesting past 16 levels as `"[max depth]"`, and invalid UTF-8 bytes are replaced.

The raw value is still what `$envelope->get(Rule::class)` and `$envelope->results()` return.

Next: [Reading a saved run](14-saved-runs.md).
