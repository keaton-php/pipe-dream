<!-- nav-group: Reference -->
# Return values and stop tables

One-glance lookups: what a rule can return, and how far a stop reaches.

## What a rule can return

| In the rule | Recorded | What happens next |
|---|---|---|
| `return self::SUCCESS;` (or return nothing) | `SUCCESS` (1) | the next pipe runs |
| `return self::FAIL;` | `FAIL` (2) | the next pipe runs; no error record is added |
| `return self::FATAL_FAIL;` | `FATAL_FAIL` (3) | the next pipe runs: only the value is recorded, with no fatal record, so `hasFatalErrors()` stays false. To stop, call `fatal()` |
| `return $this->skip('why');` | `SKIP` (4), and the reason | the next pipe runs |
| `$this->error('why');` | `FAIL` (2), and an error record | `onError()` runs, then the next pipe runs (unless errors are fatal, see below) |
| `$this->fatal('why');` | `FATAL_FAIL` (3), and a fatal record | nothing after it runs: by default the rest of the flow, with `fatalsStopThisHubOnly()` the rest of this hub |
| any other exception or PHP error, except a `PipeRuleFatal` (handled as `fatal()`) and the three below | `FAIL` (2), and an error record | as `error()` |

A closure pipe's result is whatever it returns (nothing, or the subject itself, counts as `SUCCESS`). `error()` and `fatal()` records point at the line in your rule that called them. Three exceptions are not failures and are never recorded as `FAIL`: `PipeRuleRedirect` and `PipeRuleLoadView` hand control back to your controller, and `PipeHubException`, a configuration error, is thrown straight out of the flow.

## Stopping, in one table

How far a stop reaches:

| On the hub | A rule calls `fatal()` | A rule calls `error()`, or throws an unexpected exception |
|---|---|---|
| (default) | **the whole flow stops**: the rest of this hub, every later hub, this hub's `then()` and the manager's `then()` do not run; the hub's `onFatal()` runs, then the manager's | recorded as `FAIL`; the hub's `onError()` runs, then the manager's; the next pipe runs (unless an `onError()` hook throws, see [Reacting to failures](11-error-hooks.md)) |
| `->fatalsStopThisHubOnly()` | **this hub stops**: the rest of it and its `then()` do not run, and its `onFatal()` runs (not the manager's); the next hub runs, and the manager's `then()` still runs | as default |
| `->errorsAreFatal()` | as default | handled as a `fatal()` (recorded as `FATAL_FAIL`, sent to `onFatal()` instead of `onError()`), so **the whole flow stops** |
| both | as `fatalsStopThisHubOnly()` | handled as a `fatal()`, so **this hub stops** |

How the stop reaches the caller, set on the manager (or on a hub run on its own; inside a manager a hub's own setting is ignored):

| | Default | `->throwFatals()` |
|---|---|---|
| a fatal stops the flow | `sendIt()` returns the envelope; `hasFatalErrors()` is true | `sendIt()` throws the same `PipeRuleFatal`, after it is logged, `onFatal()` runs, `then()` does not run and the log is persisted as configured (a redirect, view or configuration error thrown by `onFatal()` leaves instead) |
| a fatal is contained to its hub | the flow carries on | the flow carries on, and nothing is thrown |
| a redirect, view or configuration error | thrown from `sendIt()` | thrown from `sendIt()` |

## The details behind the tables

- "Exception" means any `Throwable`, PHP errors such as `TypeError` included.
- A rule with `protected $errorsAreFatal = true;` handles its own errors and exceptions as fatals, as if its hub used `errorsAreFatal()`.
- Redirects, views and configuration errors (`PipeHubException`) are never handled as errors or fatals: wherever they are thrown, they leave `sendIt()` as themselves (a hub or manager may write a configuration error to the log first).
- A `then()` callback, the hub's or the manager's, is handled like a pipe: an exception thrown in it is an error (recorded, sent to `onError()`, and the flow carries on), or a fatal under the hub's `errorsAreFatal()`; a `PipeRuleFatal` thrown in it is a fatal. A `skipIf()` callback or an event listener that throws stops the hub as a fatal, which reaches as far as any other.
- What a hook throws: a redirect, view or `PipeHubException` leaves `sendIt()` as itself; a `PipeRuleFatal` from `onError()` is a fatal; any other exception from `onError()` stops the hub as a fatal wrapping it; any other exception from `onFatal()` is recorded as a fatal and the stop carries on as it would have.
- A callback parameter named `$subject` gets the envelope, and a hook parameter named `$exception` gets the exception, by name; otherwise the envelope goes to the first parameter and, for hooks, the exception to the second, whatever they are called. Either way that happens only when the parameter's declared type accepts the value (an untyped parameter always does); a hook parameter typed as an exception class, whether it is named `$exception` or is the second parameter, always gets the real exception, so a mismatch is a PHP `TypeError`, never a different exception built for it.
- The fatal thrown by `throwFatals()` is the same `PipeRuleFatal` instance that was raised, so `getPrevious()` is the error or exception behind a fatal from `errorsAreFatal()`, and its `subject()` is set to the envelope before it is thrown.
- Everything above that "leaves `sendIt()`" (a fatal, a redirect, a view or a configuration error) assumes saving the log succeeds: the log is saved as the run ends, and an exception from the persistence strategy takes the place of whatever was leaving.

## Skipping, in one table

| | What runs | What is logged | `then()` |
|---|---|---|---|
| `->skipIf(fn ($s) => …)` returns true | no rule executes | every rule, as `SKIP` with the reason (plus a `PipeHub:event` line when a skip event is set) | runs |
| `->skip()` | nothing in the hub | one "Skipping hub" line (plus a `PipeHub:event` line when a skip event is set) | does not run |
| `$envelope->skipAll()` (from a rule or callback) | nothing after that point, in this hub or any later hub | every remaining rule, as `SKIP` | runs |
| a rule returns `$this->skip('…')` | the rule ran and decided to skip | that rule, as `SKIP` with the reason | runs |

Next: [Rule options](21-rule-options.md).
