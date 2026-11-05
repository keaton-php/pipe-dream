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
- `ClosurePipe`: closures added to a hub are timed, logged and recorded like rules.
- A test suite on Orchestra Testbench, run against Laravel 9, 10, 11, 12 and 13.

### Fixed

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

### Changed

- **Fatal semantics.** Previously a fatal always ended the whole flow. Now, without `stopOnFatal()`, a fatal ends the current hub (its remaining pipes and its `then()` do not run), a warning is logged, and the flow continues with the next hub. With `stopOnFatal()` the fatal ends the whole flow and the manager's `then()` does not run. Add `stopOnFatal()` to every hub whose fatals must end the flow.
- A standalone hub (`$hub->sendIt()`) no longer rethrows a fatal; it returns the envelope. Use `hasFatalErrors()`.
- `failureIsFatal()` is now a flag that works alongside `onFailure()` (it used to replace the `onFailure()` callback). A failure in that hub runs `onFailure()`, logs a fatal and ends the flow.
- `onFailure()` callbacks can also take the exception, as `$exception`.
- The hub records a stop as a `warning` (`PipeHub:sendIt`, with `context.ends` = `hub` or `flow`) rather than an `error`, so a fatal on its own no longer makes `hasErrors()` true.
- `skipIf()` now logs each skipped rule's reason and fires the `skip` event instead of `before` / `after`; skipped rules no longer fire `beforeEach` / `afterEach`.
- Per-pipe events are constructed with the envelope and the rule's key (previously the raw subject); hub events with the envelope.
- `then()` callbacks are no longer rebound to the hub: `$this` inside them is what it was where you wrote them.
- `PipeManager::sendIt()` without a subject throws `PipeHubException`, like `PipeHub::sendIt()`.
- Dependencies now declare the components the code uses: `illuminate/container`, `illuminate/contracts`, `illuminate/database`, `illuminate/support` and `ramsey/uuid`.

### Removed

- `src/Events/PipeDreamEvent.php` and `PipeDreamEvents.php` (unused, and called methods that do not exist).
- `PipeEnvelope::load()`, `evaluate()`, `setSkips()`, `setEvents()`, `broadcastEvents()`, `sendBroadcastEventByType()` and the empty destructor; `PipeHub::shouldSkipPipe()` and the empty before-pipeline hook.
