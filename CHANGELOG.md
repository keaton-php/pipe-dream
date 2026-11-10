# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Changed

- A receipt shows a duration of a second or more in seconds (`14.80 s`), not as a long count of milliseconds. `ConsoleFormat` and `MarkdownFormat` both do; `ArrayFormat` still gives `duration_ms`.
- The README leads with a click that was flagged *and* slow ("why did it take 15 seconds to redirect me?"), and shows its receipt and flow before the checkout. The click example gains a `ClickIntakeFlow`, a `ReceiptSummary` on `IncomingClick`, and a GeoIP stand-in (`GeoIp`) behind `ResolveVisitorCountry`.

## [1.2.0] - 2026-11-08

### Added

- **`$envelope->receipt()`: the run as a receipt you can print.** A `Receipt` holds the flow, its outcome (`completed`, `completed with errors`, `handed off`, `stopped`) and each hub (`ReceiptHub`) with its steps; a `ReceiptFormat` decides how it looks. `ConsoleFormat` (the default), `MarkdownFormat` and `ArrayFormat` ship with the package; implement `ReceiptFormat` for your own and bind it to make it the default. `echo $receipt` prints the default format, `format($format)` returns any format, `print($format)` echoes it. `PipeDreamLog::receipt()` gives the same receipt for a saved run.
- **`ReceiptSummary`**, an optional interface for a subject: `receiptSummary(): ?string` is recorded when the run ends and heads its receipt ("On hold: two fraud signals") in place of the generic outcome.
- **A reason for `skipIf()`.** The callback may return a non-empty string: the hub's rules are skipped with that string as their reason. A plain `true` uses the new second argument, `skipIf($callback, $reason)`, when given. `false`, `null` and `''` do not skip.
- `StepRecord::origin()`: the `file:line` of a step's first error or fatal.
- A checkout example under `docs/examples` (an `Order`, 13 rules, and held / placed / stopped runs), used by the README.

### Changed

- An error or fatal's log line is now just its message: the ` [file | line N]` suffix is gone. The file and line are still in the record's context (`context.file`, `context.line`), and on `StepRecord::origin()`.
- `ReceiptHub::wasSkipped()` is now true when every rule in a hub was skipped (as `skipIf()` does), not only when the whole hub was `skip()`ped.
- The documentation is rewritten around one checkout flow, receipt-first: every page shows a book-style flow and prints `echo $envelope->receipt()`, with no hand-built log loops. Click intake appears once, as a second walkthrough.

## [1.1.0] - 2026-11-08

### Added

- **`$envelope->steps()`: the run as a list of steps.** A `Collection` of `StepRecord` objects, one per rule (or closure pipe) that executed or was skipped, in run order. It is computed from the logger's records, so it reads the same live or rebuilt from a stored run (`PipeDreamLog::find($id)->logger()->steps()`), and hub-level log lines are never steps. Each `StepRecord` answers `name()`, `rule()`, `hub()`, `duration()` (ms), `returnValue()`, `status()` (`ok` / `skipped` / `error` / `fatal`) with `wasOk()` / `wasSkipped()` / `hasErrors()` / `wasFatal()`, `skipReason()` and `messages()` (the info, warning and error lines logged inside that step). `$envelope->logs()` is unchanged — it is the lower-level, record-by-record view.

### Changed

- The README leads with a flow and its step log, and the docs teach `steps()` first and `logs()` as the lower-level view: "Reading the log" is rewritten around steps, the "find the failing step" and stacked-hub examples use `steps()` and collections (`->first(fn ...)`, `->groupBy->hub()`), and the API reference lists `StepRecord`. No behaviour changed and `logs()` is untouched; this release adds `steps()` and documents it.

## [1.0.0] - Unreleased

### Added

- **Database persistence.** A publishable `pipe_dream_logs` migration (`--tag=pipe-dream-migrations`), the `PipeDreamLog` model (with `logger()` to rebuild a stored run, `subject()` morph, `forRun()` and `withProblems()` scopes) and `DatabasePersistStrategy`, which upserts one row per run.
- `PipeEnvelope::runId()`: a stable uuid per envelope; and `flowName()`, set from the manager's (or standalone hub's) name.
- `PipeLoggerJsonStrategy`, now the default output strategy.
- Log levels: `PipeLogger::LEVEL_EVERYTHING` (default), `LEVEL_NO_TIMERS` and `LEVEL_PROBLEMS`, applied when a record is added.
- `PipeHub::broadcastEventOnSkip()`.
- Read-only getters for tooling: `PipeManager::getName()`, `getDescription()`, `getHubs()`, `getThen()`, `getOnError()`, `getOnFatal()`, `throwsFatals()`, `persistsLogs()`; `PipeHub::getSkipIf()`, `getThen()`, `getOnError()`, `getOnFatal()`, `getEvents()`, `stopsOnlyThisHubOnFatal()`, `treatsErrorsAsFatal()`, `isSkipped()`, `throwsFatals()`, `persistsLogs()`.
- `PipeManager::persistLogs()`.
- **How far a stop reaches, set on the hub.** `PipeHub::fatalsStopThisHubOnly()` contains a hub's fatals to that hub: the manager moves on to the next hub and its `then()` still runs. `PipeHub::errorsAreFatal()` makes every `error()` and unexpected exception in the hub's pipes a fatal (and the rule-level `$errorsAreFatal` now covers unexpected exceptions too).
- **`onError()` and `onFatal()` hooks** on both `PipeHub` and `PipeManager`, called with the envelope and the exception (in the first and second parameters, whatever they are named). `onError()` runs for every error or unexpected exception a pipe records, hub first, then manager, and the next pipe runs. `onFatal()` runs when a fatal stops the hub (hub hook) or the flow (manager hook), before `sendIt()` returns or throws. A redirect or view thrown from either is handed off as usual.
- **`throwFatals()`** on `PipeManager`, and on `PipeHub` for a hub run on its own. Off by default. With it, a fatal that stops the flow is rethrown out of `sendIt()` (the same `PipeRuleFatal`, its `subject()` set to the envelope, `getPrevious()` kept) after it is logged, `onFatal()` runs, `then()` does not run and the log is persisted. A fatal contained to its hub is never thrown.
- `ClosurePipe`: closures added to a hub are timed, logged and recorded like rules.
- A test suite on Orchestra Testbench, and a GitHub Actions matrix that runs it on PHP 8.1–8.4 against Laravel 9–13.
- `Support\LogValue`, which makes every logged value JSON-safe.
- `PipeEnvelope::wrap($subject, $container)` and `PipeEnvelope::container()`.
- The README is a step-by-step tutorial, and every code example in it is run by `tests/Feature/ReadmeExamplesTest.php`.

### Fixed

- A `PipeRuleFatal` thrown from `skipIf()` or an event listener stopped the hub but left no fatal on the log, so `hasFatalErrors()` was false and the stored `has_fatals` was false. It is now recorded as a fatal, once (tracked per exception, so a rule's own fatal is never logged twice and a distinct one is never dropped).
- An exception from a listener of a skipped hub's `skip` event escaped the hub; it now stops the hub like any other failure outside the pipes.
- Method calls on the envelope now reach a subject's `__call()` (an Eloquent scope, a forwarded query method); before, only methods the subject declared were forwarded.
- PHP errors such as `TypeError` and `ArgumentCountError` escaped rules, hubs and the manager: only `\Exception` was caught, so they left no `FAIL` result and bypassed the hub's failure handling. Every `Throwable` is now handled, after the fatal, redirect and load-view cases.
- A hub given its own container resolved rules from it but called rule methods and closure pipes, and built envelopes, through the global container. Everything in a run now uses the hub's container (and a manager's envelope uses the manager's).
- A rule bound as a singleton kept one hub's `skipIf()` skip or error setting in later hubs and runs. The hub now writes its whole per-run policy on every run, separately from the rule's own settings.
- `pipe_dream_logs.subject_id` was an integer (via `nullableMorphs()`), so UUID and ULID subjects could fail. It is now a nullable string with a composite `subject_type`/`subject_id` index.
- A rule returning a resource, a recursive object, `INF` or `NAN` made persisting the log throw, which could replace a redirect or the flow's real outcome. Logged values are now made JSON-safe as they are recorded.
- A closure pipe ignored `$next`, so no pipe after it ever ran. Closures now run and the chain continues.
- `PipeEnvelope::skipAll()` set a flag nothing read. Every remaining pipe, in this hub and every later hub, is now logged as skipped and not executed.
- `PipeLogger::setLevel()` / `PipeEnvelope::logLevel()` were ignored.
- `PipeLogger::output()` and `rebuild()` crashed when no strategy was set; they now default to JSON.
- The serialize strategy now calls `unserialize()` with `allowed_classes => false`.
- A run through a `PipeManager` wrote the log once per hub. It is now persisted once, by the manager, at the end of the flow (also when it fails or redirects).
- `PipeRule` `set{X}Result()` / `get{X}Result()` called an application-specific `session()`; they now use the envelope's `set()` / `get()`.
- `PipeRule::$saveResultAs` and `$errorsAreFatal` were declared but never used. A subclass can now declare `$errorsAreFatal` (the constructor no longer overwrites class defaults).
- Rule methods only received the subject when the parameter was named `$subject`; the first parameter now gets it whatever its name, as callbacks already did.
- `PipeHub::addPipe()` silently ignored a class that does not exist or is not a `PipeRule`, and `PipeManager::addHub()` ignored anything that is not a hub. Both now throw `PipeHubException`.
- `PipeManager::create()` with no arguments threw a `TypeError`.
- `afterEach` fired in reverse order after the whole rest of the chain had run; it now fires as each pipe hands off to the next. Every event dispatch is logged with its real event type.
- `PipeEnvelope::persistWith()` misused `class_implements()`.
- `PipeEnvelope::hasErrors()` / `hasFatalErrors()` returned true for every envelope (a collection is always truthy).
- Exception handlers are matched with `instanceof` (exact class first), not by exact class only.
- `PipeRuleError` / `PipeRuleFatal` accept `?Throwable $previous`; `PipeRuleRedirect` and `PipeRuleLoadView` now have a message.
- `PipeHub::getName()` / `getDescription()` are nullable instead of throwing a `TypeError` on an unnamed hub.
- `PipeRule::skip($message)` dropped its message and could record two results.

- A standalone hub treated `false`, `0`, `''` and `[]` as a missing subject; only `null` is missing now, as for `PipeManager`.
- `DatabasePersistStrategy` read and then inserted or updated (`updateOrCreate()`), so two workers persisting one run could collide on the unique `run_id`. It now writes with a single native upsert keyed on `run_id`; `created_at` is set on insert only.
- `PipeRuleRedirect::toRoute()` built its URL through the global container. The route is now resolved by the rule, hub or manager it passes through, with the run's container.
- `$this->fatal()` / `$this->error()` records pointed at `PipeRule.php`. They now point at the rule's own call site, in the message and in the record's `context` (`file`, `line`).

- A `toRoute()` redirect thrown by the hub's failure callback left the hub without a URL. The hub now resolves a redirect from any hook with its own container, and a standalone hub and the manager resolve any redirect on the way out.
- Running a hub again from inside its own run (for another subject) made the rest of the outer run log to, and dispatch events with, the inner envelope. Each run now works only on its own envelope, and `getSubject()` describes the run that finished last.
- Callbacks were handed the envelope in their first parameter whatever its declared type (so `fn (string $label = 'x')` got an envelope). Union, intersection, nullable and builtin types are now checked first; hooks get the exception in their second parameter the same way.
- A container-shared (singleton) rule was executed directly: it kept a reference to its last envelope, could still write to a finished one, and nested or interleaved runs of it shared one subject, policy and container. Hubs now run a copy of each shared rule per run, and a rule lets go of its envelope when it finishes.
- `PipeEnvelope::get()` and `PipeLogger::recordsByKey()` treated `0`, `'0'` and `''` as "no key"; only `null` does now.

### Changed

- **Package name.** The package is `keaton-php/pipe-dream` and its namespace is `Keaton\PipeDream`; update your `composer.json` and `use` statements from the old pre-release name.
- **Fatal semantics.** A fatal stops the whole flow by default, as it did before this release: the rest of its hub, every later hub, and both the hub's and the manager's `then()` do not run. What changed is that a hub can now contain its fatals with `fatalsStopThisHubOnly()`, and that a fatal is quiet by default: `sendIt()` returns the envelope (check `hasFatalErrors()`), and `throwFatals()` rethrows it.
- A standalone hub (`$hub->sendIt()`) no longer rethrows a fatal by default; it returns the envelope. Use `hasFatalErrors()`, or `throwFatals()` to have it thrown.
- **Errors.** An `error()` or an unexpected exception in a rule is recorded as `FAIL`, reported to `onError()`, and the next pipe runs. To make errors stop things, use `errorsAreFatal()` on the hub or `$errorsAreFatal` on the rule; there is no longer a way for an error to end a hub without counting as a fatal.
- Something outside the pipes that fails (a `skipIf()` callback, an event listener, an `onError()` hook) stops the hub as a fatal wrapping the exception, instead of as a "failure". `then()` callbacks (hub and manager) are handled like pipes: an exception there is an error and the flow carries on (a manager's `then()` exception used to escape `sendIt()`), and a `PipeRuleFatal` there is a fatal. An `onFatal()` hook that throws is recorded as a fatal and never changes how the stop ends.
- The hub records a stop as a `warning` (`PipeHub:sendIt`, with `context.ends` = `hub` or `flow`) rather than an `error`, so a fatal on its own no longer makes `hasErrors()` true.
- `PipeRule`'s constructor is `__construct(?bool $errorsAreFatal = null, ?bool $shouldSkip = null)` (the `$stopOnError` argument is gone).
- `skipIf()` now logs each skipped rule's reason and fires the `skip` event instead of `before` / `after`; skipped rules no longer fire `beforeEach` / `afterEach`.
- Per-pipe events are constructed with the envelope and the rule's key (previously the raw subject); hub events with the envelope.
- `then()` callbacks are no longer rebound to the hub: `$this` inside them is what it was where you wrote them.
- `PipeManager::sendIt()` without a subject throws `PipeHubException`, like `PipeHub::sendIt()`.
- A `PipeRuleRedirect` from `toRoute()` carries `$route` and `$parameters`, and its `redirectTo` is filled in when it passes through a rule, hub or manager (or by calling `resolveRoute($container)` yourself).
- A rule bound as shared (singleton, instance, scoped) runs as a per-run clone; a transient binding runs as the object the container returned. A shared rule that cannot be cloned (private `__clone()`, or a `__clone()` that throws) makes the flow throw a `PipeHubException` naming the rule, instead of failing silently.
- `PipeHubException` (a configuration error) is never treated as a hub failure: it is logged and rethrown out of the rule, hub and manager.
- Requires PHP 8.1 or later (the versions the CI matrix covers).
- Dependencies now declare the components the code uses: `illuminate/container`, `illuminate/contracts`, `illuminate/database`, `illuminate/support` and `ramsey/uuid`.

### Removed

- `PipeHub::stopOnFatal()` (a fatal now stops the flow unless the hub uses `fatalsStopThisHubOnly()`), `stopOnError()` and `failureIsFatal()` (use `errorsAreFatal()`), `onFailure()` (use `onError()` / `onFatal()`), and their getters `stopsOnFatal()`, `stopsOnError()`, `isFailureFatal()` and `getOnFailure()`.
- `PipeRule::withoutErrorHandling()`, `$rethrowErrors`, `$stopOnError` and `setStopOnError()`. An exception no longer escapes a rule as a separate "failure" outcome; use `$errorsAreFatal` to make a rule's errors and exceptions fatal.
- `src/Events/PipeDreamEvent.php` and `PipeDreamEvents.php` (unused, and called methods that do not exist).
- `PipeEnvelope::load()`, `evaluate()`, `setSkips()`, `setEvents()`, `broadcastEvents()`, `sendBroadcastEventByType()` and the empty destructor; `PipeHub::shouldSkipPipe()` and the empty before-pipeline hook.
