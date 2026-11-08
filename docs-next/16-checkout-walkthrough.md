<!-- nav-group: Walkthroughs -->
# A full checkout

The checkout from the [introduction](01-introduction.md), stage by stage: why each piece is shaped the way it is, and how it fits in an app.

## The flow

<!-- example: docs-next/examples/CheckoutFlow.php -->
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
                ->then(fn ($order) => $order->riskSignals() >= 2 ? $order->holdForReview('Two or more fraud signals') : null),

            PipeHub::create()
                ->name('Charge')
                ->description('Take the money, only for orders we will ship')
                ->addPipes([
                    AuthorizePayment::class,
                    CapturePayment::class,
                ])
                ->skipIf(fn ($order) => $order->isOnHold() ? "Held for review: {$order->holdReason}" : null)
                ->errorsAreFatal(),

            PipeHub::create()
                ->name('Fulfil')
                ->description('Reserve stock, tell the warehouse, email the receipt')
                ->addPipes([
                    ReserveInventory::class,
                    SendToWarehouse::class,
                    EmailReceipt::class,
                ])
                ->skipIf(fn ($order) => $order->isOnHold() ? "Held for review: {$order->holdReason}" : null),

        ])
            ->name('Checkout')
            ->then(fn ($order) => $order->markPlaced());
    }
}
```

## Stage by stage

**Validate the order.** Cheap checks first, and the ones that make the rest meaningless. A missing address or an out-of-stock item is a `fatal()`: nothing after it should run, so nothing after it appears on the receipt. A missing discount code is a skip, not a failure; an invalid one is an `error()`, noted on the receipt while the order carries on.

**Fraud screen.** Each rule is one signal and only flags (`warning()` + `FAIL`). The stage's `then()` holds the policy: two signals put the order on hold. Tuning fraud means changing one number in one line that anyone can read. A trusted customer skips the stage, and the receipt says so once, on the stage's line.

**Charge.** It skips itself when the order is on hold, with the hold reason as its skip reason. `errorsAreFatal()` means any failure here (a decline, a gateway timeout, an exception) stops the flow, so Fulfil can never run for an order that wasn't paid for.

**Fulfil.** It has the same skip as Charge. The flow's `then()` marks the order placed only if nothing stopped it.

## Three receipts

| Order | Receipt header | What it shows |
|---|---|---|
| [Suspicious](03-your-first-flow.md#4-read-the-receipt) | `On hold: Two or more fraud signals` | two flags, then Charge and Fulfil skipped with the reason |
| [Trusted customer](03-your-first-flow.md#5-same-flow-different-stories) | `Placed` | the fraud screen skipped as a trusted customer, everything else ✓ |
| [Out of stock](03-your-first-flow.md#5-same-flow-different-stories) | `stopped` | one ■ at validation, and nothing after it |

## In an app

- **One class builds the flow** (`CheckoutFlow` above). The controller, a queued retry and the tests all run exactly the same thing.
- **The controller catches hand-offs and checks for a stop**, as on [Redirects and views](11-redirects-and-views.md).
- **Every run is saved** with one binding ([Saving and replaying runs](12-saving-and-replaying.md)). Put `$envelope->runId()` on the order, and support can print any order's receipt.
- **Tests assert on the receipt** ([Testing flows](13-testing.md)): `summary()`, `skipReason()`, `wasStopped()`.
- **`Order` implements `ReceiptSummary`**, so the receipt speaks the business's language ([The receipt](07-the-receipt.md#say-it-in-business-terms)).

**Next:** [A high-volume click intake](17-click-intake.md).
