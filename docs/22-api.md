<!-- nav-group: Reference -->
# API

Every public method of the classes you build flows with, one line each. Methods marked `@internal` in the source (`PipeHub::sendItWithinFlow()`, `PipeRule::applyPolicy()`, `PipeEnvelope::markFatalRecorded()` and `hasRecordedFatal()`) are not part of the public API. Methods that set something return the object, so calls chain.

## PipeHub

A named stage of a flow: an ordered list of rules or closures. [Pipes and hubs](03-pipes-and-hubs.md) shows them in use.

| Method | What it does |
|---|---|
| `PipeHub::create($container = null)` | Makes an empty hub. |
| `name($name)`, `description($text)` | Labels the hub. |
| `addPipe($pipe)`, `addPipes($pipes)` | Adds a closure or a `PipeRule` class name; throws `PipeHubException` for anything else. |
| `skipIf($callback)` | When the callback returns true, every rule is logged as skipped and none runs; `then()` still runs. |
| `skip($shouldSkip = true)` | Skips the whole hub, `then()` included. |
| `subject($subject)` | Sets the subject, for a later `sendIt()` with no argument. |
| `then($callback)` | Runs after the hub's last rule, when no fatal stopped the hub. |
| `onError($callback)`, `onFatal($callback)` | Hooks for errors and fatals, called with the envelope and the exception. |
| `fatalsStopThisHubOnly($only = true)` | A fatal ends only this hub; the manager moves on. |
| `errorsAreFatal($fatal = true)` | Every `error()` and unexpected exception in the hub counts as a fatal. |
| `throwFatals($shouldThrow = true)` | For a hub run on its own: rethrow the stopping fatal out of `sendIt()`. |
| `persistLogs($persist = true)` | For a hub run on its own: save the log when it finishes (the default). |
| `broadcastEventBefore($event)`, `broadcastEventAfter($event)`, `broadcastEventOnSkip($event)`, `broadcastEventBeforeEachPipe($event)`, `broadcastEventAfterEachPipe($event)` | Dispatch a Laravel event at that point ([Events](16-events.md)). |
| `sendIt($subject = null)` | Runs the hub and returns the envelope. |
| `wrapInEnvelope($subject)` | Wraps a subject in an envelope (or returns an envelope unchanged). |
| `getName()`, `getDescription()`, `getPipes()`, `getInstantiatedPipes()`, `getSubject()` | Read back what the hub holds. |
| `getSkipIf()`, `getThen()`, `getOnError()`, `getOnFatal()`, `getEvents()` | Read back the callbacks and events. |
| `stopsOnlyThisHubOnFatal()`, `treatsErrorsAsFatal()`, `isSkipped()`, `persistsLogs()`, `throwsFatals()` | Read back the hub's settings. |

## PipeManager

Runs several hubs, in order, on one envelope: one run, one log. [Stacking hubs with a manager](08-managers.md) shows it in use.

| Method | What it does |
|---|---|
| `PipeManager::create($hubs = [], $container = null)` | Makes a manager, optionally with its hubs. |
| `name($name)`, `description($text)` | Labels the flow; the name becomes `$envelope->flowName()`. |
| `addHub($hub)`, `addHubs($hubs)` | Adds hubs; throws `PipeHubException` for anything that is not a `PipeHub`. |
| `subject($subject)` | Sets the subject. |
| `then($callback)` | Runs after the last hub, unless a fatal stopped the flow. |
| `onError($callback)`, `onFatal($callback)` | Hooks for errors in any hub and for a fatal that stops the flow. |
| `persistLogs($persist = true)` | Saves the log once when the flow ends (the default). |
| `throwFatals($shouldThrow = true)` | Rethrows the fatal that stopped the flow out of `sendIt()`. |
| `sendIt()` | Runs every hub and returns the envelope. |
| `callback($callback = null, $parameters = [])` | Calls a callback through the container, passing the subject. |
| `hubs()`, `getHubs()` | The hubs, in order. |
| `getName()`, `getDescription()`, `getThen()`, `getOnError()`, `getOnFatal()`, `persistsLogs()`, `throwsFatals()` | Read back the manager's settings. |

## PipeEnvelope

What a pipe receives: the subject, the shared values, the results and the log. [The envelope](04-envelope.md) shows it in use.

| Method | What it does |
|---|---|
| `PipeEnvelope::wrap($subject, $container = null)` | Wraps a subject, or returns an envelope unchanged. |
| `subject()` | The wrapped subject. |
| `set($key, $value)`, `get($key = null)`, `result($key = null)` | Writes and reads values shared between pipes (and rule results); `get()` with no key returns them all. |
| `$envelope->name`, `$envelope->name = …`, `$envelope->method()` | Reads, writes and method calls go through to the subject. |
| `runId()` | A uuid that identifies the run. |
| `flowName()`, `setFlowName($name)` | The flow's name. |
| `results()` | The rule results (`PipeRuleResults`). |
| `logger()`, `logs()` | The `PipeLogger`, and its records. |
| `hasErrors()`, `hasFatalErrors()` | Whether an error or fatal was recorded. |
| `logLevel($level)` | Sets the log level (`PipeLogger::LEVEL_*`). |
| `skipAll()`, `skipsAll()` | Skips every remaining pipe; whether that was asked. |
| `persistWith($strategy)`, `persist()`, `persistLogs($strategy = null)` | Sets the persist strategy and saves the run. |
| `container()` | The container the envelope resolves its services from. |
| `addExceptionHandler($exception, $handler)` | Registers a handler for a custom exception class thrown in a rule. |
| `register($rule)`, `unregister($rule)`, `registerException($e, $type, $rule)`, `saveRuleResult($result)` | Called by the hub as it runs a rule; you rarely call them yourself. |

## PipeRule

The base class of a rule. [Writing rules](06-rules.md) shows it in use.

| Member | What it does |
|---|---|
| `SUCCESS` (1), `FAIL` (2), `FATAL_FAIL` (3), `SKIP` (4) | The result constants. |
| `static $name`, `static $description`, `static $runFunction`, `static $saveResultAs` | Label the rule, rename its run method, store its result under another key. |
| `$this->info($msg)`, `warning($msg)` | Write a log line against the rule. |
| `$this->error($msg)`, `fatal($msg)` | Record a failure, or a fatal that stops things. |
| `$this->skip($msg = null)` | Skip the rule and return `SKIP`. |
| `$this->foo`, `setFooResult()`, `getFooResult()` | Write and read a shared value on the envelope. |
| `shouldSkip($skip = true, $reason = null)`, `willSkip($subject)`, `wasSkipped()` | Mark a rule to be skipped, and ask whether it is or was. |
| `ruleKey()` | The key the rule's records and result are stored under. |
| `setContainer($container)` | Sets the container the rule's method is called through. |
| `getExtendedClassMethods()` | The public methods declared on the rule's own class (not inherited ones). |
| `execute($subject)`, `handle($subject, $next)` | Run the rule; called by the hub. |

## Results

| Method | What it does |
|---|---|
| `PipeRuleResult::rule()`, `result()` | The rule's key and what it recorded. |
| `PipeRuleResult::isSuccess()`, `isFail()`, `isFatal()` | Test the recorded value. |
| `PipeRuleResults::successes()`, `failures()`, `fatalFailures()`, `skips()` | Filter the results (a `Collection`). |
| `PipeRuleResults::hasFailures()`, `hasFatalFailures()` | Whether any failed. |

## PipeLogger

[Reading the log](05-reading-the-log.md) shows the slices in use.

| Method | What it does |
|---|---|
| `records()`, `recordsByType($type)`, `recordsByKey($key = null)` | The records, filtered. |
| `infos()`, `warnings()`, `errors()`, `fatals()`, `timers()`, `returnValues()` | One slice per record type. |
| `info($key, $data)`, `warning()`, `error()`, `fatal()`, `returnValue()`, `timerStart()`, `timerEnd()` | Add a record. |
| `setLevel($level)`, `level()` | The log level. |
| `output($strategy = null)`, `rebuild($data, $strategy = null)` | Write the records in a format, and load them back. |
| `setStrategy($strategy)`, `strategy()` | The format strategy. |
| `persistLogsWith($strategy)`, `persist($envelope, $strategy = null)` | Set the persist strategy, and save a run. |

## Saving

[Saving logs to the database](13-persisting.md) and [Choosing a persist strategy](15-persist-strategies.md) show these in use.

| Member | What it does |
|---|---|
| `PipeLoggerPersistStrategy::persist($envelope)` | The contract a persist strategy implements. |
| `PipeLoggerStrategy::output($records)`, `rebuild($data)` | The contract a log format implements. |
| `DatabasePersistStrategy::__construct($format = null, $model = PipeDreamLog::class)` | Saves a run as one row. |
| `PipeLoggerJsonStrategy`, `PipeLoggerSerializeStrategy` | The two formats that ship. |
| `PipeDreamLog::logger($strategy = null)`, `subject()` | Rebuild a stored run; the model it ran for. |
| `PipeDreamLog::forRun($runId)`, `withProblems()` | Query scopes. |

## Exceptions

| Class | What it is |
|---|---|
| `PipeRuleRedirect::toUrl($url)`, `toRoute($name, $subject = null, $parameters = [])` | Throw to hand a redirect to your controller. |
| `PipeRuleLoadView::load($view, $data = [])` | Throw to hand a view to your controller. |
| `PipeRuleFatal` (`subject()`, `withSubject()`) | The fatal a flow stops with; thrown by `throwFatals()`. |
| `PipeHubException` | A configuration error, thrown straight out of the flow. |

Next: [Compatibility, changelog and license](23-compatibility.md).
