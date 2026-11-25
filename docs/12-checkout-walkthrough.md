<!-- nav-group: Walkthroughs -->
# Checkout walkthrough

The checkout from the earlier pages, kept in one class, with the reason for each choice and where it sits in an app.

## The flow

`build()` returns the flow without a subject, so every caller runs the same thing:

```php
<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

class CheckoutFlow
{
    public static function build(): PipeManager
    {
        return PipeManager::create([
            PipeHub::create()
                ->name('Validate the order')
                ->description('Is this a complete order for items we can actually ship?')
                ->addPipes([
                    RequireShippingAddress::class,
                    ConfirmItemsInStock::class,
                    ApplyDiscountCodes::class,
                    CalculateTax::class,
                ]),
            PipeHub::create()
                ->name('Fraud screen')
                ->description('Does this look like the real cardholder?')
                ->addPipes([
                    MatchBillingToShippingCountry::class,
                    CheckCardVelocity::class,
                    ScoreDeviceFingerprint::class,
                ])
                ->skipIf(fn ($order) => $order->customerIsTrusted(), 'Trusted customer')
                ->then(fn ($order) => $order->riskSignals() >= 2
                    ? $order->holdForReview('Two or more fraud signals')
                    : null),
            PipeHub::create()
                ->name('Charge')
                ->description('Take the money, only for orders we will ship')
                ->addPipes([
                    AuthorizePayment::class,
                    CapturePayment::class,
                ])
                ->skipIf(fn ($order) => $order->isOnHold(), 'Order is on hold')
                ->errorsAreFatal(),
            PipeHub::create()
                ->name('Fulfill')
                ->description('Reserve stock, tell the warehouse, email the receipt')
                ->addPipes([
                    ReserveInventory::class,
                    SendToWarehouse::class,
                    EmailReceipt::class,
                ])
                ->skipIf(fn ($order) => $order->isOnHold(), 'Order is on hold'),
        ])
            ->name('Checkout')
            ->then(fn ($order) => $order->markPlaced());
    }
}
```

## Stage by stage

Validate the order comes first because its checks are cheap, and a failure there makes the rest pointless. A missing address or a sold-out item is a `fatal()`: nothing after it should run, so nothing after it appears on the receipt. A missing discount code is a skip. An invalid one is an `error()`, noted on the receipt while the order carries on.

In the fraud screen, each rule is one signal and only flags, with `warning()` and `FAIL`. The stage's `then()` holds the policy: two signals put the order on hold. Tuning the screen means changing one number on one line. A trusted customer skips the stage, and the receipt says so once, on the stage's line.

Charge skips itself when the order is on hold. `errorsAreFatal()` means any failure here, whether a decline, a gateway timeout or an exception, stops the flow. Fulfill cannot run for an order that was not paid for.

Fulfill has the same skip as Charge. The flow's `then()` marks the order placed only if nothing stopped it.

## Three receipts

| Order | Receipt header | What it shows |
|---|---|---|
| [`ord_48213`](index.md) | `On hold: Two or more fraud signals` | two flags, then Charge and Fulfill skipped with the reason |
| [`ord_48214`](03-your-first-flow.md) | `Placed` | the fraud screen skipped for a trusted customer, `ApplyDiscountCodes` skipped (no code), every other step ✓ |
| [`ord_48215`](07-pass-flag-skip-stop.md) | `stopped` | one ■ at validation, and nothing after it |

## In an app

- The controller, a queued retry and the tests all call `CheckoutFlow::build()`.
- The controller catches hand-offs and checks for a stop, as in [Redirects and views](08-redirects-and-views.md).
- Every run is saved with one binding ([Saving and replaying runs](09-saving-and-replaying.md)). Store `$envelope->runId()` on the order, and support can print that order's receipt.
- Tests assert on the receipt ([Testing flows](10-testing.md)).
- `Order` implements `ReceiptSummary`, so the header is in the shop's own words ([The receipt](06-the-receipt.md)).

## Read the flow back

A flow can be read without running it. A page that documents or draws the flow can be built from the same object that runs in production:

```php
$flow = CheckoutFlow::build();

echo $flow->getName(), PHP_EOL;

echo collect($flow->getHubs())
    ->map(fn ($hub) => sprintf(
        '%s: %d rules%s',
        $hub->getName(),
        count($hub->getPipes()),
        $hub->getSkipIf() ? ', can be skipped' : '',
    ))
    ->implode(PHP_EOL);
```

```text
Checkout
Validate the order: 4 rules
Fraud screen: 3 rules, can be skipped
Charge: 2 rules, can be skipped
Fulfill: 3 rules, can be skipped
```

The manager has `getName()`, `getDescription()`, `getHubs()`, `getThen()`, `getOnError()`, `getOnFatal()`, `throwsFatals()` and `persistsLogs()`. A hub has the getters listed on the [API](13-api.md) page. A rule class has its static `$name` and `$description`.

**Next:** [API](13-api.md).
