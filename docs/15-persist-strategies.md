<!-- nav-group: Keeping and reading logs -->
# Choosing a persist strategy

Where should logs be saved, and in what format?

Two separate choices decide it: **where** the log goes (a persist strategy) and **how** it is written (a format).

## Where: the persist strategy

| Option | What it does | Choose it when |
|---|---|---|
| `DatabasePersistStrategy` | Saves one row per run in `pipe_dream_logs`, written with a single upsert keyed on the run id. | You want to query past runs: which had problems, which ran for one subject. |
| Your own class implementing `PipeLoggerPersistStrategy` | Does whatever `persist(PipeEnvelope $subject)` does. `$subject->logger()->output()` gives you the log as JSON. | The log belongs somewhere else: a file, a log service, a queue. |
| None (the default) | Nothing is written. The log lives on the envelope and is gone when the request ends. | You only need the log for the current request, or in a test. |

Set it per run with `$envelope->persistWith()`, or bind `PipeLoggerPersistStrategy` in the container once to use it for every flow. `->persistLogs(false)` on a manager (or a standalone hub) stops the automatic save, so you can call `$envelope->persist()` yourself.

## How: the format

| Option | What it does | Choose it when |
|---|---|---|
| `PipeLoggerJsonStrategy` | Writes the records as JSON. It is the default. | Almost always: the log is readable by any tool, and rebuilds as plain arrays. |
| `PipeLoggerSerializeStrategy` | Writes the records with PHP `serialize()`. It rebuilds without ever instantiating classes: an object comes back as `__PHP_Incomplete_Class`. | You specifically want PHP's serialized form. |

`new DatabasePersistStrategy(new PipeLoggerSerializeStrategy())` stores the serialized form instead of JSON; pass the same strategy to `logger()` to read it back. A custom format implements `PipeLoggerStrategy`, which has two methods, `output($records)` and `rebuild($records)`.

## Not sure?

Use `DatabasePersistStrategy` with the default JSON format, and bind it once in a service provider.

Next: [Events](16-events.md).
