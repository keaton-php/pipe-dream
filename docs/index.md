<!-- nav-group: Start here -->
# Introduction

Pipe Dream turns your business logic into a flow you can read, and every run into a receipt you can read back: every step named, timed and recorded.

## A flow reads like a book

Here is a whole checkout. You've never seen this codebase, and you already know what it does:

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

## Every run hands you a receipt

Send a suspicious order through it and ask what happened. Nobody wrote a log line:

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

That's every rule that ran, what it decided, why, and how long it took. It also shows what *didn't* happen: the card was never charged and nothing shipped. When support asks "why is this order on hold?", this is the answer, and it took one line: `echo $envelope->receipt();`.

## Four words

That's the whole vocabulary:

```text
Flow      "Checkout"               PipeManager: the stages, in order
 └ Hub    "Fraud screen"           PipeHub: one named stage
    └ Rule  CheckCardVelocity      PipeRule: one small class, one decision
Receipt   $envelope->receipt()     what every rule decided, and why
```

Your object (the order) travels through the flow inside an **envelope**, which carries it along with the record of the run. That's all you need to know to start.

## Why it's worth it

- **The code is the documentation.** The flow above is the table of contents of your checkout. A new hire reads it in a minute; a product manager can review it.
- **Every run explains itself.** The receipt is built from what actually ran, so it can't drift from the code the way comments and wiki pages do.
- **Every rule is a small class.** One decision, one file, one test. Change the fraud screen without touching the charge.
- **Save it, and the receipt outlives the request.** Pull any order back up weeks later and print the same receipt.
- **It's not ad-tech-specific, or checkout-specific.** Loan decisions, KYC onboarding, insurance claims, refunds, content moderation, click intake at hundreds of millions of requests: if it's a series of decisions someone will later ask you to explain, it's a flow.

## What it isn't

It's not a queue or a workflow engine. A flow runs in order, in one request, and returns its envelope. Use it inside a job, a controller or a command; it doesn't care.

**Next:** [Install it](02-installation.md), then [build this checkout yourself](03-your-first-flow.md) in about ten minutes.
