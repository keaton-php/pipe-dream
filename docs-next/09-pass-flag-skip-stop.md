<!-- nav-group: Deciding what happens -->
# Pass, flag, skip, stop

Every step ends in one of four ways, and the receipt shows which. This page is about choosing the right one.

## The four endings

| Ending | Symbol | In a rule | Use it when |
|---|---|---|---|
| pass | ✓ | `return self::SUCCESS;` | the check is fine |
| flag | ✗ | `$this->warning('why'); return self::FAIL;` or `$this->error('why');` | something is off, but the flow should carry on |
| skip | ↷ | `return $this->skip('why');` | there's nothing for this rule to do |
| stop | ■ | `$this->fatal('why');` | carrying on would be wrong |

The checkout uses all four. `ConfirmItemsInStock` stops: shipping half an order is worse than not shipping. The fraud rules flag: one signal isn't enough to refuse an order, so the hub's `then()` decides once it has seen all of them. `ApplyDiscountCodes` skips when there's no code.

## Flag, then decide

The pattern behind the fraud screen is worth copying: rules **flag**, and the stage **decides**.

```text
->addPipes([MatchBillingToShippingCountry::class, CheckCardVelocity::class, ScoreDeviceFingerprint::class])
->then(fn ($order) => $order->riskSignals() >= 2 ? $order->holdForReview('Two or more fraud signals') : null)
```

Each rule stays small and honest about one signal, and the policy ("two signals means a hold") lives in one readable line. The later stages then skip themselves with the reason.

## Skipping

Four ways, from the smallest to the biggest:

| How | What runs | On the receipt |
|---|---|---|
| a rule returns `$this->skip('why')` | the rule ran and decided it had nothing to do | that rule ↷, with its reason |
| `->skipIf(fn ($order) => ...)` on a hub | none of the hub's rules | every rule ↷; the reason once on the hub's line |
| `->skip()` on a hub | nothing in the hub, and not its `then()` | one "Skipping hub" record |
| `$envelope->skipAll()` from a rule or callback | nothing after that point, in any hub | every remaining rule ↷ |

What a `skipIf()` closure returns decides whether the hub runs and what the receipt says:

| The closure returns | The hub | The reason on the receipt |
|---|---|---|
| a non-empty string | is skipped | that string |
| `true` | is skipped | the `$reason` argument, if you gave one |
| `false`, `null` or `''` | runs | |

The checkout uses both styles: `skipIf(fn ($order) => $order->customerIsTrusted(), 'Trusted customer')` has one reason, and `skipIf(fn ($order) => $order->isOnHold() ? "Held for review: {$order->holdReason}" : null)` builds its reason from the order. A closure can return a different string for each reason it might skip.

## Stopping

A fatal stops the whole flow by default. Two hub settings change how far a failure reaches:

| You want | In the rule | On the hub |
|---|---|---|
| note it and keep going | `$this->error('why')` | nothing |
| end this stage and move on to the next | `$this->fatal('why')` | `->fatalsStopThisHubOnly()` |
| stop everything | `$this->fatal('why')` | nothing: this is the default |
| make every failure in a stage stop everything | `$this->error('why')`, or an exception | `->errorsAreFatal()` |

The checkout's Charge stage uses `errorsAreFatal()`: if authorizing the card fails for any reason, the order must not go on to Fulfil. A stopped flow's later stages never run, so they aren't on the receipt, and the flow's `then()` doesn't run either:

<!-- output: docs-next/examples/scripts/checkout-out-of-stock.php -->
```text
Checkout · stopped (0.22 ms)

Validate the order — Is this a complete order for items we can actually ship?
  ✓ RequireShippingAddress (0.02 ms)
  ■ ConfirmItemsInStock    (0.13 ms)  Out of stock: Limited-run poster
```

Every combination, including what `then()` and the hooks do in each case, is on [Outcome tables](18-outcome-tables.md).

**Next:** [Reacting to failures](10-reacting-to-failures.md).
