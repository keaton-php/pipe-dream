<!-- nav-group: Start here -->
# Your first flow

Build the checkout from the [introduction](index.md), send three orders through it, and read what each one did. About ten minutes.

## 1. The thing that flows

A flow works on your own object. Here it's an `Order`: a few fields (`items`, `shippingCountry`, `billingCountry`, `discountCode`, ...) and a few methods the flow calls (`customerIsTrusted()`, `addRiskSignal()`, `holdForReview()`, `isOnHold()`, `markPlaced()`). It's a plain PHP class, nothing to extend: [docs/examples/Order.php](https://github.com/keaton-php/pipe-dream/blob/1.x/docs/examples/Order.php).

## 2. Write a rule

A rule is one small class that makes one decision. The hub calls the method named after the class, and the rule ends in one of four ways:

| The rule... | Write | On the receipt |
|---|---|---|
| passes | `return self::SUCCESS;` | ✓ |
| raises a concern, and the flow carries on | `$this->warning('why'); return self::FAIL;` or `$this->error('why');` | ✗ |
| has nothing to do | `return $this->skip('why');` | ↷ |
| stops the flow | `$this->fatal('why');` | ■ |

Here's the stock check:

<!-- example: docs/examples/ConfirmItemsInStock.php -->
```php
<?php

use Illuminate\Support\Str;
use Keaton\PipeDream\PipeRule;

class ConfirmItemsInStock extends PipeRule
{
    public static $name = 'Confirm items in stock';
    public static $description = 'Every item in the cart can actually ship';

    public function confirmItemsInStock($order, Warehouse $warehouse)
    {
        $missing = array_filter($order->items, fn ($item) => ! $warehouse->inStock($item['sku']));

        if ($missing !== []) {
            $this->fatal('Out of stock: '.implode(', ', array_column($missing, 'name')));
        }

        $this->info(count($order->items).' '.Str::plural('item', count($order->items)).' in stock');

        return self::SUCCESS;
    }
}
```

- The first parameter is your order (inside its envelope).
- Every parameter after it comes from Laravel's container, so the `Warehouse` is injected.
- `$this->info()` writes the note you'll see next to the rule on the receipt.

## 3. Arrange rules into a flow

Group rules into named **hubs** (the stages) and stack the hubs in a **manager** (the flow). How each stage behaves is written on the stage itself: `skipIf()` to skip it, `then()` to act once it's done, `errorsAreFatal()` to make any failure stop the flow.

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

`sendIt()` runs it and returns the envelope: your order, plus the record of everything that happened.

## 4. Read the receipt

Send a suspicious order through (a US card shipping to the Netherlands, after four cards were tried in ten minutes) and echo its receipt:

<!-- example: docs/examples/scripts/checkout-held.php -->
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

require __DIR__.'/checkout.php';
echo $envelope->receipt();
```

<!-- output: docs/examples/scripts/checkout-held.php -->
```text
Checkout · On hold: Two or more fraud signals (2.62 ms)

Validate the order — Is this a complete order for items we can actually ship?
  ✓ RequireShippingAddress        (0.07 ms)
  ✓ ConfirmItemsInStock           (1.79 ms)  3 items in stock
  ✓ ApplyDiscountCodes            (0.03 ms)  SPRING20 applied: -$24.00
  ✓ CalculateTax                  (0.02 ms)  Tax: $7.92

Fraud screen — Does this look like the real cardholder?
  ✗ MatchBillingToShippingCountry (0.02 ms)  Billing US, shipping NL
  ✗ CheckCardVelocity             (0.01 ms)  4 cards tried on this account in 10 minutes
  ✓ ScoreDeviceFingerprint        (0.01 ms)

Charge — Take the money, only for orders we will ship · skipped: Held for review: Two or more fraud signals
  ↷ AuthorizePayment              skipped
  ↷ CapturePayment                skipped

Fulfil — Reserve stock, tell the warehouse, email the receipt · skipped: Held for review: Two or more fraud signals
  ↷ ReserveInventory              skipped
  ↷ SendToWarehouse               skipped
  ↷ EmailReceipt                  skipped
```

Read it top to bottom: the fraud screen raised two concerns, its `then()` put the order on hold, and Charge and Fulfil were skipped, each saying why. Nothing was charged. The header says "On hold" because `Order` implements the optional `ReceiptSummary` interface; [The receipt](07-the-receipt.md) shows how.

## 5. Same flow, different stories

A trusted customer's order skips the fraud screen and goes all the way through:

<!-- output: docs/examples/scripts/checkout-trusted.php -->
```text
Checkout · Placed (0.28 ms)

Validate the order — Is this a complete order for items we can actually ship?
  ✓ RequireShippingAddress        (0.02 ms)
  ✓ ConfirmItemsInStock           (0.02 ms)  1 item in stock
  ↷ ApplyDiscountCodes            skipped    No discount code
  ✓ CalculateTax                  (0.01 ms)  Tax: $5.36

Fraud screen — Does this look like the real cardholder? · skipped: Trusted customer
  ↷ MatchBillingToShippingCountry skipped
  ↷ CheckCardVelocity             skipped
  ↷ ScoreDeviceFingerprint        skipped

Charge — Take the money, only for orders we will ship
  ✓ AuthorizePayment              (0.01 ms)
  ✓ CapturePayment                (0.01 ms)

Fulfil — Reserve stock, tell the warehouse, email the receipt
  ✓ ReserveInventory              (0.01 ms)
  ✓ SendToWarehouse               (0.01 ms)
  ✓ EmailReceipt                  (0.01 ms)
```

An order with something out of stock stops at the first stage. Nothing after the fatal runs, so the later stages don't appear at all:

<!-- output: docs/examples/scripts/checkout-out-of-stock.php -->
```text
Checkout · stopped (0.22 ms)

Validate the order — Is this a complete order for items we can actually ship?
  ✓ RequireShippingAddress (0.02 ms)
  ■ ConfirmItemsInStock    (0.13 ms)  Out of stock: Limited-run poster
```

One flow, three outcomes, and each receipt explains itself.

## Next

- [Rules](04-rules.md): everything a rule can do.
- [Saving and replaying runs](12-saving-and-replaying.md): pull any order's receipt back up weeks later.
- [Testing flows](13-testing.md): assert on the receipt.
