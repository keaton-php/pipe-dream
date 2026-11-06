# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- **Database persistence.** A publishable `pipe_dream_logs` migration (`--tag=pipe-dream-migrations`), the `PipeDreamLog` model (with `logger()` to rebuild a stored run, `subject()` morph, `forRun()` and `withProblems()` scopes) and `DatabasePersistStrategy`, which upserts one row per run.
- `PipeEnvelope::runId()`: a stable uuid per envelope; and `flowName()`, set from the manager's (or standalone hub's) name.
- `PipeLoggerJsonStrategy`, now the default output strategy.
- Log levels: `PipeLogger::LEVEL_EVERYTHING` (default), `LEVEL_NO_TIMERS` and `LEVEL_PROBLEMS`, applied when a record is added.
- `PipeHub::broadcastEventOnSkip()`.
- Read-only getters for tooling: `PipeManager::getName()`, `getDescription()`, `getHubs()`, `getThen()`; `PipeHub::getSkipIf()`, `getThen()`, `getOnFailure()`, `getEvents()`, `stopsOnFatal()`, `stopsOnError()`, `isFailureFatal()`, `isSkipped()`, `persistsLogs()`.
- `PipeManager::persistLogs()`.
- `PipeManager::throwFatals()` and `PipeHub::throwFatals()` (for a hub run on its own), with `throwsFatals()` getters. Off by default. With it, the fatal that ended the flow is rethrown out of `sendIt()` (the same `PipeRuleFatal`, its `subject()` the envelope) after it is logged, `then()` is skipped and the log is persisted. In a manager only a fatal that ends the flow (`stopOnFatal()`, `failureIsFatal()`) is thrown; a hub run on its own throws any fatal.
- `ClosurePipe`: closures added to a hub are timed, logged and recorded like rules.
- A test suite on Orchestra Testbench, and a GitHub Actions matrix that runs it on PHP 8.1–8.4 against Laravel 9–13.
- `Support\LogValue`, which makes every logged value JSON-safe.
- `PipeEnvelope::wrap($subject, $container)` and `PipeEnvelope::container()`.
- The README is a step-by-step tutorial, and every code example in it is run by `tests/Feature/ReadmeExamplesTest.php`.

### Fixed

- PHP errors such as `TypeError` and `ArgumentCountError` escaped rules, hubs and the manager: only `\Exception` was caught, so they left no `FAIL` result and skipped `onFailure()` and `failureIsFatal()`. Every `Throwable` is now handled, after the fatal, redirect and load-view cases.
- A hub given its own container resolved rules from it but called rule methods and closure pipes, and built envelopes, through the global container. Everything in a run now uses the hub's container (and a manager's envelope uses the manager's).
- A rule bound as a singleton kept one hub's `skipIf()` skip or `stopOnError()` setting in later hubs and runs. The hub now writes its whole per-run policy on every run, separately from the rule's own settings, and `withoutErrorHandling()` lasts for one run.
- `pipe_dream_logs.subject_id` was an integer (via `nullableMorphs()`), so UUID and ULID subjects could fail. It is now a nullable string with a composite `subject_type`/`subject_id` index.
- A rule returning a resource, a recursive object, `INF` or `NAN` made persisting the log throw, which could replace a redirect or the flow's real outcome. Logged values are now made JSON-safe as they are recorded.
- A closure pipe ignored `$next`, so no pipe after it ever ran. Closures now run and the chain continues.
- `stopOnFatal()` was stored and never read. See "Changed" for the semantics it now has.
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

- A `toRoute()` redirect thrown by `onFailure()` left the hub without a URL. The hub resolves it with its own container, and a standalone hub and the manager resolve any redirect on the way out.
- A container-shared (singleton) rule was executed directly: it kept a reference to its last envelope, could still write to a finished one, and nested or interleaved runs of it shared one subject, policy and container. Hubs now run a copy of each shared rule per run, and a rule lets go of its envelope when it finishes.
- `PipeEnvelope::get()` and `PipeLogger::recordsByKey()` treated `0`, `'0'` and `''` as "no key"; only `null` does now.

### Changed

- **Package name.** The package is `keaton-php/pipe-dream` and its namespace is `Keaton\PipeDream`; update your `composer.json` and `use` statements from the old pre-release name.
- **Fatal semantics.** Previously a fatal always ended the whole flow. Now, without `stopOnFatal()`, a fatal ends the current hub (its remaining pipes and its `then()` do not run), a warning is logged, and the flow continues with the next hub. With `stopOnFatal()` the fatal ends the whole flow and the manager's `then()` does not run. Add `stopOnFatal()` to every hub whose fatals must end the flow, and `throwFatals()` to the manager to catch that fatal around `sendIt()`.
- A standalone hub (`$hub->sendIt()`) no longer rethrows a fatal by default; it returns the envelope. Use `hasFatalErrors()`, or `throwFatals()` to have it thrown.
- `failureIsFatal()` is now a flag that works alongside `onFailure()` (it used to replace the `onFailure()` callback). A failure in that hub runs `onFailure()`, logs a fatal and ends the flow.
- `onFailure()` callbacks can also take the exception, as `$exception`.
- The hub records a stop as a `warning` (`PipeHub:sendIt`, with `context.ends` = `hub` or `flow`) rather than an `error`, so a fatal on its own no longer makes `hasErrors()` true.
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

- `src/Events/PipeDreamEvent.php` and `PipeDreamEvents.php` (unused, and called methods that do not exist).
- `PipeEnvelope::load()`, `evaluate()`, `setSkips()`, `setEvents()`, `broadcastEvents()`, `sendBroadcastEventByType()` and the empty destructor; `PipeHub::shouldSkipPipe()` and the empty before-pipeline hook.
