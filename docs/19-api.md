<!-- nav-group: Reference -->
# API

Every public method, grouped by the object you call it on. All live under `Keaton\PipeDream`.

## PipeManager (a flow)

| Method | Does |
|---|---|
| `PipeManager::create(array $hubs = [], ?Container $app = null)` | Make a flow from hubs, in order. |
| `addHub($hub)`, `addHubs(array $hubs)` | Add more hubs. |
| `name($name)`, `description($text)` | Label the flow. |
| `subject($subject)` | What the flow is about: your object, or an envelope you wrapped. |
| `then(Closure)` | Run once after the last hub, unless a fatal stopped the flow. |
| `onError(Closure)`, `onFatal(Closure)` | Hooks for every hub's errors, and for a fatal that stops the flow. |
| `throwFatals($throw = true)` | Throw the `PipeRuleFatal` from `sendIt()` instead of returning. |
| `persistLogs($persist = true)` | Save when the flow ends (default on, if a strategy is set). |
| `sendIt()` | Run every hub; returns the `PipeEnvelope`. |
| `getName()`, `getDescription()`, `getHubs()`, `getThen()`, `getOnError()`, `getOnFatal()`, `throwsFatals()`, `persistsLogs()` | Read the flow back. |

## PipeHub (a stage)

| Method | Does |
|---|---|
| `PipeHub::create(?Container $app = null)` | Make an empty hub. |
| `name()`, `description()` | Label the stage. |
| `addPipe($pipe)`, `addPipes(array $pipes)` | Add rule classes or closures. |
| `skipIf(Closure $callback, ?string $reason = null)` | Skip the stage's rules: a string return is the reason, `true` uses `$reason`. |
| `skip($skip = true)` | Skip the whole stage. |
| `then(Closure)` | Run after the stage's last rule. |
| `errorsAreFatal($fatal = true)` | Treat every error in the stage as a fatal. |
| `fatalsStopThisHubOnly($only = true)` | Contain a fatal to this stage. |
| `onError(Closure)`, `onFatal(Closure)` | Hooks for this stage. |
| `broadcastEventBefore()`, `…After()`, `…OnSkip()`, `…BeforeEachPipe()`, `…AfterEachPipe()` | Dispatch events. |
| `throwFatals()`, `persistLogs()` | As on the manager, for a hub run on its own. |
| `sendIt($subject)` | Run the hub on its own; returns the `PipeEnvelope`. |
| `getName()`, `getDescription()`, `getPipes()`, `getSkipIf()`, `getSkipIfReason()`, `getThen()`, `getOnError()`, `getOnFatal()`, `getEvents()`, `stopsOnlyThisHubOnFatal()`, `treatsErrorsAsFatal()`, `isSkipped()`, `throwsFatals()`, `persistsLogs()` | Read the hub back. |

## PipeRule (a rule)

| Member | Does |
|---|---|
| `SUCCESS` (1), `FAIL` (2), `FATAL_FAIL` (3), `SKIP` (4) | Result values. |
| `$this->info()`, `warning()`, `error()`, `fatal()` | Write a note; `error()` records a failure; `fatal()` stops. |
| `return $this->skip('why')` | Skip this rule, with a reason. |
| `$this->foo`, `$this->foo = …`, `getFooResult()`, `setFooResult()` | Read and write shared values on the envelope. |
| `static $name`, `$description`, `$runFunction`, `$saveResultAs` | Settings. |
| `protected $errorsAreFatal` | Treat this rule's errors as fatals. |

## PipeEnvelope (what flows)

| Method | Does |
|---|---|
| `PipeEnvelope::wrap($subject)` | Make the envelope yourself, before the run. |
| `subject()` | Your object. |
| `receipt()` | The run's `Receipt`. |
| `runId()`, `flowName()` | The run's uuid, and the flow's name. |
| `get($key)`, `set($key, $value)` | Shared values, and rule results by class name. |
| `results()` | Each rule's result, as a collection with `successes()`, `failures()`, `fatalFailures()`, `skips()`. |
| `hasErrors()`, `hasFatalErrors()` | How the run went. |
| `skipAll()` | Skip everything after this point. |
| `persistWith($strategy)`, `persist()` | Save this run with a strategy; save it now. |
| `steps()`, `logs()`, `logger()` | Lower-level views of the log. |

## Receipt

| Method | Does |
|---|---|
| `format(?ReceiptFormat $format = null)` | The formatted output (default format if none). |
| `print(?ReceiptFormat $format = null)` | Echo it. |
| `(string) $receipt`, `echo $receipt` | The default format, as text. |
| `flowName()`, `runId()`, `duration()` | About the run. Durations are in milliseconds. |
| `outcome()` | `Receipt::COMPLETED`, `COMPLETED_WITH_ERRORS`, `HANDED_OFF` or `STOPPED`. |
| `summary()` | The subject's `ReceiptSummary`, or `null`. |
| `wasStopped()`, `hasErrors()`, `wasHandedOff()` | Shortcuts for the outcome. |
| `hubs()` | A collection of `ReceiptHub`. |
| `steps()` | A collection of `StepRecord`, in run order. |
| `Receipt::defaultFormat()` | The `ReceiptFormat` bound in the container, or `ConsoleFormat`. |

**`ReceiptHub`:** `name()`, `description()`, `steps()`, `skipReason()` (when every rule was skipped for the same reason), `wasSkipped()` (true only for `skip()`), `duration()`.

**`StepRecord`:** `name()`, `rule()`, `hub()`, `status()` (`ok`, `skipped`, `error`, `fatal`), `wasOk()`, `wasSkipped()`, `hasErrors()`, `wasFatal()`, `skipReason()`, `messages()`, `origin()` (`file:line` of the first error or fatal), `returnValue()`, `duration()`.

## Formats and contracts

| | |
|---|---|
| `Formats\ConsoleFormat(colors: false, times: true, descriptions: true, origins: false, runId: false)` | Plain text. |
| `Formats\MarkdownFormat(times, descriptions, origins, runId)` | A Markdown table per hub. |
| `Formats\ArrayFormat` | A nested array. |
| `Contracts\ReceiptFormat` | `format(Receipt $receipt): mixed`. Bind it to change the default. |
| `Contracts\ReceiptSummary` | `receiptSummary(): ?string`, optional, on your subject. |
| `Contracts\PipeLoggerPersistStrategy` | `persist(PipeEnvelope $envelope)`: where runs are saved. |
| `Contracts\PipeLoggerStrategy` | `output($records)`, `rebuild($data)`: how logs are encoded. |

## Saved runs

| | |
|---|---|
| `Models\PipeDreamLog::forRun($runId)` | Scope: one run. |
| `PipeDreamLog::withProblems()` | Scope: runs with errors or fatals. |
| `$run->receipt(?PipeLoggerStrategy $strategy = null)` | The saved run's receipt. |
| `$run->logger()`, `$run->subject` | The rebuilt log; the Eloquent model it was about. |
| `Strategies\DatabasePersistStrategy`, `PipeLoggerJsonStrategy`, `PipeLoggerSerializeStrategy` | The built-in strategies. |

## Exceptions

| | |
|---|---|
| `Exceptions\PipeRuleRedirect::toUrl($url)`, `::toRoute($name)` | Hand off a redirect. |
| `Exceptions\PipeRuleLoadView::load($view, $data)` | Hand off a view. |
| `Exceptions\PipeRuleFatal` | A fatal; thrown from `sendIt()` under `throwFatals()`. `subject()` is the envelope. |
| `Exceptions\PipeHubException` | A configuration error, such as adding a class that isn't a rule. |

**Next:** [Compatibility](20-compatibility.md).
