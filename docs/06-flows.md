<!-- nav-group: Building blocks -->
# Flows

A flow is hubs stacked in order: one subject, one run, one receipt.

## Stack the stages

`PipeManager::create([...])` takes the hubs in the order they run. Name the flow, give it the subject, and send it:

<!-- example: docs/examples/scripts/checkout.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$envelope = PipeManager::create([

    PipeHub::create()
        ->name('Validate the order')
        ->description('Is this a complete order for items we can actually ship?')
        ->addPipes([
            RequireShippingAddress::class,
            ConfirmItemsInStock::class,
            ApplyDiscountCodes::class,
            CalculateTax::class,
        ]),                           // a broken order ends the whole flow here

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
        ->errorsAreFatal(),           // a failed charge ends the flow

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
    ->subject($order)
    ->then(fn ($order) => $order->markPlaced())
    ->sendIt();
```

Every hub works on the same envelope, so a value one stage leaves (the risk signals) is there for the next (the hold check). The flow's `then()` runs once, after the last hub, and only if nothing stopped the flow: that's why a stopped order is never marked placed.

## Keep the flow in a class

In an app, the flow usually lives in one class that builds it, so the controller, the queue job and the tests all run the same flow:

<!-- example: docs/examples/CheckoutFlow.php -->
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

`build()` returns the manager without running it. Give it a subject and send it wherever you need it:

<!-- example: docs/examples/scripts/flow-class.php -->
```php
<?php

$order = new Order(
    id: 'ord_48213',
    items: [
        ['sku' => 'tee-blk-m', 'name' => 'Black tee', 'price' => 30.00],
        ['sku' => 'hoodie-gry-l', 'name' => 'Grey hoodie', 'price' => 65.00],
        ['sku' => 'cap-nvy', 'name' => 'Navy cap', 'price' => 25.00],
    ],
    shippingAddress: 'Keizersgracht 123, Amsterdam',
    shippingCountry: 'NL',
    billingCountry: 'US',
    discountCode: 'SPRING20',
    cardsTriedRecently: 4,
);

$envelope = CheckoutFlow::build()
    ->subject($order)
    ->sendIt();

echo $envelope->receipt()->format(new OneLineFormat);
```

<!-- output: docs/examples/scripts/flow-class.php -->
```text
Checkout: On hold: Two or more fraud signals (5 ok, 2 flagged, 5 skipped)
```

`OneLineFormat` is a custom receipt format, built on [The receipt](07-the-receipt.md#your-own-format).

## What a flow takes

A manager has the same shape as a hub: `name()`, `description()`, `then()`, `onError()`, `onFatal()`, `throwFatals()` and `persistLogs()`. `subject()` sets what the flow is about, and `sendIt()` runs every hub and returns one envelope.

## How far a failure reaches

By default an `error()` is noted and the flow carries on, and a `fatal()` stops everything. Two settings on a hub change that: `errorsAreFatal()` (the checkout's Charge stage uses it: a failed charge ends the flow) and `fatalsStopThisHubOnly()` (a fatal ends that stage, and the next one runs). [Pass, flag, skip, stop](09-pass-flag-skip-stop.md) shows all of it.

**Next:** [The receipt](07-the-receipt.md).
