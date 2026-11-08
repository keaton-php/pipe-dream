<!-- nav-group: In production -->
# Saving and replaying runs

Save every run, and any order's receipt comes back whole, weeks later: the answer to "why was this order held?" is one query away.

## Turn it on

Run the migration from [Installation](02-installation.md), then bind the database strategy once, in a service provider. Every flow saves itself from then on, one row per run:

<!-- example: docs-next/examples/scripts/save-and-replay.php -->
```php
<?php

use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Models\PipeDreamLog;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;

// Once, in a service provider: save every run.
app()->bind(PipeLoggerPersistStrategy::class, DatabasePersistStrategy::class);

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

$runId = CheckoutFlow::build()->subject($order)->sendIt()->runId();

// Weeks later, from a support ticket:
echo PipeDreamLog::forRun($runId)->first()->receipt();
```

<!-- output: docs-next/examples/scripts/save-and-replay.php -->
```text
Checkout · On hold: Two or more fraud signals (0.27 ms)

Validate the order — Is this a complete order for items we can actually ship?
  ✓ RequireShippingAddress        (0.02 ms)
  ✓ ConfirmItemsInStock           (0.03 ms)  3 items in stock
  ✓ ApplyDiscountCodes            (0.02 ms)  SPRING20 applied: -$24.00
  ✓ CalculateTax                  (0.01 ms)  Tax: $7.92

Fraud screen — Does this look like the real cardholder?
  ✗ MatchBillingToShippingCountry (0.01 ms)  Billing US, shipping NL
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

The saved receipt is the live one: same steps, same notes, same "On hold" header.

## When it saves

A flow saves once, when it ends, however it ended: completed, stopped, or thrown. A hub run on its own saves when it finishes. Saving the same run again updates its row. To save one run only, wrap the subject yourself: `PipeEnvelope::wrap($order)->persistWith(new DatabasePersistStrategy())`. To save by hand, call `->persistLogs(false)` on the flow and `$envelope->persist()` when you're ready.

## Find runs

`PipeDreamLog` is an ordinary Eloquent model:

| You want | Ask |
|---|---|
| one run | `PipeDreamLog::forRun($runId)->first()` |
| every run with an error or a fatal | `PipeDreamLog::withProblems()->get()` |
| runs of one flow | `PipeDreamLog::where('flow', 'Checkout')` |
| the model a run was about | `$run->subject` (when the subject was a saved Eloquent model) |
| its receipt | `$run->receipt()` |

Each row has the run id, the flow's name, the log, `has_errors` and `has_fatals`, and the subject's type and id.

## Where and how it's written

Two separate choices: **where** the log goes (a persist strategy) and **how** it's encoded (a log strategy).

| Where | Choose it when |
|---|---|
| `DatabasePersistStrategy` | you want to query past runs. The usual choice. |
| your own `PipeLoggerPersistStrategy` | the log belongs in a file, a log service or a queue. `persist(PipeEnvelope $envelope)` gets the envelope; `$envelope->logger()->output()` is the log as JSON. |
| none (the default) | you only need the receipt during the request, or in a test. |

| How | Choose it when |
|---|---|
| `PipeLoggerJsonStrategy` (the default) | almost always. |
| `PipeLoggerSerializeStrategy` | you specifically want PHP's `serialize()` form. Read it back with the same strategy: `$run->receipt(new PipeLoggerSerializeStrategy())`. |

Every value is made JSON-safe as it's recorded (objects, enums, dates, `NAN`, cycles), so a strange return value can never stop a run from being saved.

**Next:** [Testing flows](13-testing.md).
