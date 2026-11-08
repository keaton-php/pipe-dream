<!-- nav-group: Building blocks -->
# The receipt

Ask a run what happened. `$envelope->receipt()` is the run, hub by hub and step by step, built from what actually ran.

## Print it

```text
echo $envelope->receipt();
```

That's the whole call behind every receipt in these docs. A `Receipt` is data (the flow, how it ended, each hub, each step); a **format** decides what it looks like. Echoing a receipt uses the default format, `ConsoleFormat`.

## Pick a format

Pass a format to `format()` to get the output back, or to `print()` to echo it.

| Format | Gives you | Options |
|---|---|---|
| `ConsoleFormat` | plain text (the default) | `colors`, `times`, `descriptions`, `origins`, `runId` |
| `MarkdownFormat` | a table per hub, for a ticket, a PR or Slack | `times`, `descriptions`, `origins`, `runId` |
| `ArrayFormat` | a plain array, for an API or a dashboard | none |

The held order, ready to paste into a support ticket:

<!-- example: docs/examples/scripts/receipt-markdown.php -->
```php
<?php

use Keaton\PipeDream\Formats\MarkdownFormat;

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

$envelope = CheckoutFlow::build()->subject($order)->sendIt();

echo $envelope->receipt()->format(new MarkdownFormat(times: false));
```

<!-- output: docs/examples/scripts/receipt-markdown.php -->
```markdown
**Checkout** · On hold: Two or more fraud signals

**Validate the order** — Is this a complete order for items we can actually ship?

| | Step | Notes |
| :-: | --- | --- |
| ✓ | RequireShippingAddress |  |
| ✓ | ConfirmItemsInStock | 3 items in stock |
| ✓ | ApplyDiscountCodes | SPRING20 applied: -$24.00 |
| ✓ | CalculateTax | Tax: $7.92 |

**Fraud screen** — Does this look like the real cardholder?

| | Step | Notes |
| :-: | --- | --- |
| ✗ | MatchBillingToShippingCountry | Billing US, shipping NL |
| ✗ | CheckCardVelocity | 4 cards tried on this account in 10 minutes |
| ✓ | ScoreDeviceFingerprint |  |

**Charge** — Take the money, only for orders we will ship · skipped: Held for review: Two or more fraud signals

| | Step | Notes |
| :-: | --- | --- |
| ↷ | AuthorizePayment |  |
| ↷ | CapturePayment |  |

**Fulfil** — Reserve stock, tell the warehouse, email the receipt · skipped: Held for review: Two or more fraud signals

| | Step | Notes |
| :-: | --- | --- |
| ↷ | ReserveInventory |  |
| ↷ | SendToWarehouse |  |
| ↷ | EmailReceipt |  |
```

The same receipt as data, for an API. Here's its fraud screen:

<!-- example: docs/examples/scripts/receipt-array.php -->
```php
<?php

use Keaton\PipeDream\Formats\ArrayFormat;

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

$envelope = CheckoutFlow::build()->subject($order)->sendIt();

$receipt = $envelope->receipt()->format(new ArrayFormat);

echo json_encode($receipt['hubs'][1], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
```

<!-- output: docs/examples/scripts/receipt-array.php -->
```json
{
    "name": "Fraud screen",
    "description": "Does this look like the real cardholder?",
    "skipped": false,
    "skip_reason": null,
    "duration_ms": 0.031,
    "steps": [
        {
            "name": "MatchBillingToShippingCountry",
            "rule": "MatchBillingToShippingCountry",
            "status": "error",
            "duration_ms": 0.014,
            "messages": [
                "Billing US, shipping NL"
            ],
            "origin": null
        },
        {
            "name": "CheckCardVelocity",
            "rule": "CheckCardVelocity",
            "status": "error",
            "duration_ms": 0.01,
            "messages": [
                "4 cards tried on this account in 10 minutes"
            ],
            "origin": null
        },
        {
            "name": "ScoreDeviceFingerprint",
            "rule": "ScoreDeviceFingerprint",
            "status": "ok",
            "duration_ms": 0.007,
            "messages": [],
            "origin": null
        }
    ]
}
```

## Where did it break?

`origins: true` adds the file and line each error or fatal was raised on, for the engineer reading the receipt:

<!-- example: docs/examples/scripts/receipt-origins.php -->
```php
<?php

use Keaton\PipeDream\Formats\ConsoleFormat;

$order = new Order(
    id: 'ord_48215',
    items: [
        ['sku' => 'tee-blk-m', 'name' => 'Black tee', 'price' => 30.00],
        ['sku' => 'sold-out', 'name' => 'Limited-run poster', 'price' => 40.00],
    ],
    shippingAddress: '500 Congress Ave, Austin',
    shippingCountry: 'US',
    billingCountry: 'US',
);

$envelope = CheckoutFlow::build()->subject($order)->sendIt();

echo $envelope->receipt()->format(new ConsoleFormat(times: false, origins: true));
```

<!-- output: docs/examples/scripts/receipt-origins.php -->
```text
Checkout · stopped

Validate the order — Is this a complete order for items we can actually ship?
  ✓ RequireShippingAddress
  ■ ConfirmItemsInStock     Out of stock: Limited-run poster
                            at docs/examples/ConfirmItemsInStock.php:16
```

(The real output shows the absolute path.)

## Say it in business terms

The header says how the run ended: `completed`, `completed with errors`, `stopped` or `handed off`. That's all the package can know. Only your order knows it's "on hold".

To say so, implement the optional `Keaton\PipeDream\Contracts\ReceiptSummary` interface on your subject. It has one method, `receiptSummary(): ?string`. The checkout's `Order` returns `"On hold: {$this->holdReason}"` for a held order, `'Placed'` for a placed one, and `null` otherwise. When it returns `null`, or the subject doesn't implement the interface, the header keeps the generic outcome. That's why the out-of-stock order reads `stopped`.

The summary is recorded when the run ends, so a saved run prints the same header later. If `receiptSummary()` throws, the error is recorded as a warning and the run carries on.

## Ask it questions

A receipt is also something you can query in code:

<!-- example: docs/examples/scripts/receipt-questions.php -->
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

$receipt = CheckoutFlow::build()->subject($order)->sendIt()->receipt();

echo $receipt->outcome(), PHP_EOL;                                            // how it ended
echo $receipt->summary(), PHP_EOL;                                            // what the order says
echo $receipt->steps()->first(fn ($step) => $step->hasErrors())->name(), PHP_EOL;  // the first concern
echo $receipt->hubs()->filter->skipReason()->map->name()->implode(', '), PHP_EOL;  // what never ran
```

<!-- output: docs/examples/scripts/receipt-questions.php -->
```text
completed with errors
On hold: Two or more fraud signals
MatchBillingToShippingCountry
Charge, Fulfil
```

`hubs()` is a collection of `ReceiptHub` (`name()`, `description()`, `steps()`, `skipReason()`, `duration()`), and `steps()` is a collection of `StepRecord` (`name()`, `status()`, `messages()`, `origin()`, `duration()`, ...). Everything is on the [API page](19-api.md).

## Your own format

A format is one method, `format(Receipt $receipt): mixed`. This one squeezes a run into one line, for a log or a Slack alert:

<!-- example: docs/examples/OneLineFormat.php -->
```php
<?php

use Keaton\PipeDream\Contracts\ReceiptFormat;
use Keaton\PipeDream\Receipt;

class OneLineFormat implements ReceiptFormat
{
    public function format(Receipt $receipt): string
    {
        $steps = $receipt->steps();

        return sprintf(
            '%s: %s (%d ok, %d flagged, %d skipped)',
            $receipt->flowName(),
            $receipt->summary() ?? $receipt->outcome(),
            $steps->filter->wasOk()->count(),
            $steps->filter->hasErrors()->count(),
            $steps->filter->wasSkipped()->count(),
        );
    }
}
```

`$envelope->receipt()->format(new OneLineFormat)` prints `Checkout: On hold: Two or more fraud signals (5 ok, 2 flagged, 5 skipped)`. To make your format what `echo $receipt` prints everywhere, bind it in a service provider: `$this->app->bind(ReceiptFormat::class, OneLineFormat::class);`.

## Saved runs

A run [saved to the database](12-saving-and-replaying.md) gives you the same receipt back, weeks later: `PipeDreamLog::forRun($runId)->first()->receipt()`.

## Under the receipt

The receipt is built from the run's log. Two lower-level views are there when you need them:

- `$envelope->results()`: each rule's result value, for checks in code.
- `$envelope->logs()`: every raw record (timers, notes, skips, events), for debugging the package itself.

**Next:** [The envelope](08-the-envelope.md).
