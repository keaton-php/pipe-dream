<!-- nav-group: Reference -->
# Outcome tables

One-glance lookups: what a rule can return, how far a stop reaches, and what skipping does.

## What a rule can return

| In the rule | Recorded | What happens next |
|---|---|---|
| `return self::SUCCESS;`, or nothing | `SUCCESS` (1) | the next step runs |
| `return self::FAIL;` | `FAIL` (2) | the next step runs; no error record is added |
| `return self::FATAL_FAIL;` | `FATAL_FAIL` (3) | the next step runs: only the value is recorded, and `hasFatalErrors()` stays false. To stop, call `fatal()` |
| `return $this->skip('why');` | `SKIP` (4), with the reason | the next step runs |
| `$this->error('why');` | `FAIL` (2), with an error record | `onError()` runs, then the next step (unless errors are fatal) |
| `$this->fatal('why');` | `FATAL_FAIL` (3), with a fatal record | nothing after it runs: the rest of the flow, or with `fatalsStopThisHubOnly()` the rest of this hub |
| any other exception or PHP error | `FAIL` (2), with an error record | as `error()` |

A closure's result is whatever it returns; nothing, or the subject itself, counts as `SUCCESS`. Redirects, views and `PipeHubException` (a configuration error) are never failures: they leave `sendIt()` as themselves.

## How far a stop reaches

| On the hub | A rule calls `fatal()` | A rule calls `error()`, or throws |
|---|---|---|
| (default) | **the whole flow stops**. The rest of this hub, later hubs, this hub's `then()` and the flow's `then()` don't run. The hub's `onFatal()` runs, then the flow's. | recorded as `FAIL`; the hub's `onError()`, then the flow's; the next step runs |
| `fatalsStopThisHubOnly()` | **this hub stops**. The rest of it and its `then()` don't run, and its `onFatal()` runs (not the flow's). The next hub runs, and the flow's `then()` still runs. | as default |
| `errorsAreFatal()` | as default | handled as `fatal()`: **the whole flow stops** |
| both | as `fatalsStopThisHubOnly()` | handled as `fatal()`: **this hub stops** |

A rule with `protected $errorsAreFatal = true;` behaves as if its hub had `errorsAreFatal()`.

## How the stop reaches the caller

These are set on the flow, or on a hub run on its own. Inside a flow, a hub's own `throwFatals()` is ignored.

| | Default | `throwFatals()` |
|---|---|---|
| a fatal stops the flow | `sendIt()` returns; `hasFatalErrors()` is true | `sendIt()` throws the same `PipeRuleFatal`, after logging, hooks and saving; `$e->subject()` is the envelope |
| a fatal is contained to its hub | the flow carries on | the flow carries on, nothing is thrown |
| a redirect, view or configuration error | thrown from `sendIt()` | thrown from `sendIt()` |

## Skipping

| How | What runs | On the receipt | The hub's `then()` |
|---|---|---|---|
| `skipIf()` returns a string | no rule in the hub | every rule ↷, with that string as the reason | runs |
| `skipIf()` returns `true` | no rule in the hub | every rule ↷, with the `$reason` argument, or a generic reason | runs |
| `skipIf()` returns `false`, `null` or `''` | everything | | runs |
| `skip()` | nothing in the hub | one "Skipping hub" record | doesn't run |
| `$envelope->skipAll()` | nothing after that point, in any hub | every remaining rule ↷ | runs |
| a rule returns `$this->skip('why')` | the rule ran and chose to skip | that rule ↷, with its reason | runs |

## What a hook may throw

- A redirect, view or `PipeHubException`, from either hook, leaves `sendIt()` as itself.
- A `PipeRuleFatal` from `onError()` escalates the error to a fatal.
- Any other exception from `onError()` stops the hub as a fatal wrapping it.
- Any other exception from `onFatal()` is recorded as a fatal, and the stop carries on as it would have.
- `then()` callbacks are handled like steps: an exception in one is an error, or a fatal under `errorsAreFatal()`.

## Callback parameters

A callback parameter named `$subject` gets the envelope, and a hook parameter named `$exception` gets the exception. Otherwise the envelope goes to the first parameter and, for hooks, the exception to the second.

**Next:** [API](19-api.md).
