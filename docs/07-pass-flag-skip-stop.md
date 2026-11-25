<!-- nav-group: Deciding what happens -->
# Pass, flag, skip, stop

Every step ends in one of four ways, and the receipt shows which: pass ✓, flag ✗, skip ↷ or stop ■. This page is about choosing between them, and about what runs afterwards.

## What a rule can do

| In the rule | Recorded | What happens next |
|---|---|---|
| `return self::SUCCESS;`, or return nothing | `SUCCESS` (1) ✓ | the next step runs |
| `$this->warning('why'); return self::FAIL;` | `FAIL` (2) ✗ | the next step runs, and no error record is added |
| `$this->error('why');` | `FAIL` (2) ✗, with an error record | `onError()` runs, then the next step |
| `return $this->skip('why');` | `SKIP` (4) ↷, with the reason | the next step runs |
| `$this->fatal('why');` | `FATAL_FAIL` (3) ■, with a fatal record | nothing after it runs |
| any other exception or PHP error | as `error()` | as `error()` |
| `return self::FATAL_FAIL;` | only the value | the next step runs. To stop, call `fatal()` |

A closure's result is what it returns. Nothing, or the subject itself, counts as `SUCCESS`.

The checkout uses all four endings. `ConfirmItemsInStock` stops, because shipping half an order is worse than shipping none. `ApplyDiscountCodes` skips when there is no code. The fraud rules flag, and the stage decides: one signal is not enough to refuse an order, so each rule reports its own signal and the hub's `then()` holds the order once two have been raised. The policy sits in one line of the flow, and the later stages skip themselves with a reason.

## Skipping

| How | What runs | On the receipt | The hub's `then()` |
|---|---|---|---|
| a rule returns `$this->skip('why')` | the rule ran and chose to skip | that rule ↷, with its reason | runs |
| `skipIf()` returns a non-empty string | no rule in the hub | every rule ↷, with the string once on the hub's line | runs |
| `skipIf()` returns `true` | no rule in the hub | every rule ↷, with the `$reason` argument if you gave one | runs |
| `skipIf()` returns `false`, `null` or `''` | everything | | runs |
| `skip()` on a hub | nothing in the hub | one "Skipping hub" record | does not run |
| `$envelope->skipAll()` | nothing after that point, in any hub | every remaining rule ↷ | runs |

A stage with one reason to skip passes it as the second argument, as the checkout does with `'Trusted customer'`. A stage with several returns the string that applies, as the [click intake](11-click-intake.md) does.

## How far a stop reaches

| On the hub | A rule calls `fatal()` | A rule calls `error()`, or throws |
|---|---|---|
| (default) | The whole flow stops. The rest of this hub, the later hubs, this hub's `then()` and the flow's `then()` do not run. The hub's `onFatal()` runs, then the flow's. | Recorded as `FAIL`. The hub's `onError()` runs, then the flow's, then the next step. |
| `fatalsStopThisHubOnly()` | This hub stops. The rest of it and its `then()` do not run, and its `onFatal()` runs. The next hub runs, and so does the flow's `then()`. | as default |
| `errorsAreFatal()` | as default | handled as `fatal()`: the whole flow stops |
| both | as `fatalsStopThisHubOnly()` | handled as `fatal()`: this hub stops |

A rule with `protected $errorsAreFatal = true;` behaves as if its hub had `errorsAreFatal()`. The checkout's Charge stage uses `errorsAreFatal()`: if authorizing the card fails for any reason, the order must not reach Fulfill.

## React to a failure

Hubs and managers take two hooks. `onError(fn ($order, $exception) => ...)` runs for every `error()` and unexpected exception, and then the next step runs. `onFatal(fn ($order, $fatal) => ...)` runs when a fatal stops things. A hub's hook hears its own rules. A manager's hook hears every hub's, after the hub's own.

Order `ord_48215` has a sold-out item. The hook on the flow fires once, when the flow stops:

```php
$envelope = CheckoutFlow::build()
    ->subject(Orders::find('ord_48215'))
    ->onFatal(function ($order, $fatal) {
        echo "Alert for {$order->id}: {$fatal->getMessage()}", PHP_EOL, PHP_EOL;
    })
    ->sendIt();

echo $envelope->receipt();
```

```text
Alert for ord_48215: Out of stock: Limited-run poster

Checkout · stopped (0.10 ms)

Validate the order: Is this a complete order for items we can actually ship?
  ✓ RequireShippingAddress (0.02 ms)
  ■ ConfirmItemsInStock    (0.03 ms)  Out of stock: Limited-run poster
```

The stages after the fatal did not run, so they are not on the receipt, and the flow's `then()` did not mark the order placed. Hooks run before `sendIt()` returns, so a note a hook writes is saved with the run.

What a hook throws changes what happens next:

- A redirect, a view or a `PipeHubException` leaves `sendIt()` as itself, from either hook.
- A `PipeRuleFatal` thrown from `onError()` turns the error into a fatal.
- Any other exception from `onError()` stops the hub as a fatal that wraps it.
- Any other exception from `onFatal()` is recorded as a fatal, and the stop carries on as it would have.
- A `then()` callback is handled like a step: an exception in one is an error, or a fatal under `errorsAreFatal()`.

## Callback parameters

A callback parameter named `$subject` gets the envelope, and a hook parameter named `$exception` gets the exception. Otherwise the envelope goes to the first parameter and, for hooks, the exception to the second.

**Next:** [Redirects and views](08-redirects-and-views.md).
