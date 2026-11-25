<!-- nav-group: Start here -->
# Your first flow

This page builds the checkout from the [introduction](index.md), sends another order through it and reads the receipt. A checkout is the easiest flow to build from nothing. [Click intake](11-click-intake.md) builds a click flow the same way, at full size.

## The object that flows

A flow works on your own object, and almost anything can go down the pipeline: a small standalone class, or a full Laravel model. Here it is an `Order` model, with a few fields (`items`, `shippingCountry`, `billingCountry`, `discountCode`) and a few methods the flow calls (`customerIsTrusted()`, `addRiskSignal()`, `holdForReview()`, `isOnHold()`, `markPlaced()`). `Orders::find()` returns one of four sample orders. So that this page runs without a database, those sample orders, and the rules this page does not show, live in [tests/Fixtures/Docs/Checkout.php](https://github.com/keaton-php/pipe-dream/blob/1.x/tests/Fixtures/Docs/Checkout.php).

## Write a rule

A rule is one small class that makes one decision. This is the stock check:

```php
<?php

use Illuminate\Support\Str;
use Keaton\PipeDream\PipeRule;

class ConfirmItemsInStock extends PipeRule
{
    public function confirmItemsInStock($order, Warehouse $warehouse)
    {
        $soldOut = $warehouse->soldOut($order->items);

        if ($soldOut !== []) {
            $this->fatal('Out of stock: '.implode(', ', $soldOut));
        }

        $count = count($order->items);
        $this->info($count.' '.Str::plural('item', $count).' in stock');

        return self::SUCCESS;
    }
}
```

The hub calls the method named after the class. The first parameter is always your subject, here the order, inside its envelope. Call it `$subject`, or name it after what it is, like `$order`: a parameter named `$subject` gets it by name, and otherwise the first parameter gets it, whatever it is called. Every parameter after it comes from Laravel's container, so the `Warehouse` is injected. `$this->info()` writes the note shown beside the rule on the receipt, and `$this->fatal()` stops the flow. [Rules and the envelope](04-rules.md) covers the rest.

## Arrange the rules into a flow

Group the rules into named hubs, the stages, and give the hubs to a manager, the flow. How a stage behaves is written on the stage: `skipIf()` skips it, `then()` acts once its rules are done, `errorsAreFatal()` makes any failure stop the flow. `sendIt()` runs the flow and returns the envelope: your order, and the record of the run.

```php
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

$order = Orders::find('ord_48214');

$envelope = PipeManager::create([
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
    ->subject($order)
    ->then(fn ($order) => $order->markPlaced())
    ->sendIt();

echo $envelope->receipt();
```

```text
Checkout · Placed (0.26 ms)

Validate the order: Is this a complete order for items we can actually ship?
  ✓ RequireShippingAddress        (0.02 ms)
  ✓ ConfirmItemsInStock           (0.02 ms)  1 item in stock
  ↷ ApplyDiscountCodes            skipped    No discount code
  ✓ CalculateTax                  (0.01 ms)  Tax: $5.36

Fraud screen: Does this look like the real cardholder? · skipped: Trusted customer
  ↷ MatchBillingToShippingCountry skipped
  ↷ CheckCardVelocity             skipped
  ↷ ScoreDeviceFingerprint        skipped

Charge: Take the money, only for orders we will ship
  ✓ AuthorizePayment              (0.01 ms)
  ✓ CapturePayment                (0.01 ms)

Fulfill: Reserve stock, tell the warehouse, email the receipt
  ✓ ReserveInventory              (0.01 ms)
  ✓ SendToWarehouse               (0.01 ms)
  ✓ EmailReceipt                  (0.01 ms)
```

## Read the receipt

Order `ord_48214` is from a customer the shop trusts. Read its receipt from the top. Each line is one rule: whether it passed, how long it took, and the note it left. The discount rule skipped itself because the order has no code. The whole fraud screen was skipped, and the receipt says why. The card was charged and the order went to the warehouse.

The header says "Placed" because `Order` implements the optional `ReceiptSummary` interface, which [The receipt](06-the-receipt.md) covers. The same flow gives other orders other receipts. An order that looks like fraud is put on hold before the charge, as the [introduction](index.md) shows. An order with a sold-out item stops at the first stage, as [Pass, flag, skip, stop](07-pass-flag-skip-stop.md) shows.

**Next:** [Rules and the envelope](04-rules.md).
