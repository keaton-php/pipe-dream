<!-- nav-group: Building blocks -->
# The receipt

`$envelope->receipt()` is the run, hub by hub and step by step, built from what ran. `echo $envelope->receipt();` prints it, and that one call is behind every receipt on these pages.

Save the run, and the same receipt comes back from the database later, along with everything a dashboard needs. [Saving and replaying runs](09-saving-and-replaying.md#every-run-is-a-row-so-dashboards-are-easy) shows how.

## Pick a format

A `Receipt` is data: the flow, how it ended, each hub, each step. A format decides how it looks. Pass one to `format()` to get the result back, or to `print()` to echo it.

| Format | Gives you | Options |
|---|---|---|
| `ConsoleFormat` | plain text, the default | `colors`, `times`, `descriptions`, `origins`, `runId` |
| `MarkdownFormat` | a table per hub, for a ticket or a pull request | `times`, `descriptions`, `origins`, `runId` |
| `ArrayFormat` | a plain array, for an API or a dashboard | none |

Order `ord_48215` has a sold-out item. This prints its receipt twice: as text with `origins: true`, which adds the file and line each error or fatal was raised on, and as Markdown.

```php
use Keaton\PipeDream\Formats\ConsoleFormat;
use Keaton\PipeDream\Formats\MarkdownFormat;

$receipt = CheckoutFlow::build()
    ->subject(Orders::find('ord_48215'))
    ->sendIt()
    ->receipt();

echo $receipt->format(new ConsoleFormat(times: false, origins: true)), PHP_EOL;
echo $receipt->format(new MarkdownFormat(times: false));
```

```text
Checkout · stopped

Validate the order: Is this a complete order for items we can actually ship?
  ✓ RequireShippingAddress
  ■ ConfirmItemsInStock     Out of stock: Limited-run poster
                            at ConfirmItemsInStock.php:13

**Checkout** · stopped

**Validate the order**: Is this a complete order for items we can actually ship?

| | Step | Notes |
| :-: | --- | --- |
| ✓ | RequireShippingAddress |  |
| ■ | ConfirmItemsInStock | Out of stock: Limited-run poster |
```

Line 13 is the `fatal()` call of the rule as [Your first flow](03-your-first-flow.md) shows it. In your app the receipt prints the full path of the file. `ArrayFormat` returns the same receipt as a nested array of hubs and steps. Each step has `name`, `rule`, `status`, `duration_ms`, `messages` and `origin`.

## Say it in business terms

The header says how the run ended: `completed`, `completed with errors`, `stopped` or `handed off`. That is all the package can know. Only the order knows that it is on hold.

To say so, implement the optional `Keaton\PipeDream\Contracts\ReceiptSummary` interface on your subject. It has one method, `receiptSummary(): ?string`. The checkout's `Order` returns `"On hold: {$this->holdReason}"` for an order on hold, `'Placed'` for a placed one and `null` otherwise. With `null`, or with a subject that does not implement the interface, the header keeps the generic outcome, as the stopped order above does.

The summary is recorded when the run ends, so a saved run prints the same header later. If `receiptSummary()` throws, the run records a warning and carries on.

## Ask it questions

A receipt can be queried in code:

```php
$receipt = CheckoutFlow::build()
    ->subject(Orders::find('ord_48213'))
    ->sendIt()
    ->receipt();

echo $receipt->outcome(), PHP_EOL;
echo $receipt->summary(), PHP_EOL;
echo $receipt->steps()->first(fn ($step) => $step->hasErrors())->name(), PHP_EOL;
echo $receipt->hubs()->filter->skipReason()->map->name()->implode(', '), PHP_EOL;
```

```text
completed with errors
On hold: Two or more fraud signals
MatchBillingToShippingCountry
Charge, Fulfill
```

The four lines are how the run ended, what the order says about it, the first rule that raised a concern, and the hubs that were skipped. `hubs()` is a collection of `ReceiptHub` and `steps()` a collection of `StepRecord`. Their methods are on the [API page](13-api.md).

## Your own format

A format is one method, `format(Receipt $receipt): mixed`. This one puts a run on one line, for a log or a chat alert:

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

```php
$envelope = CheckoutFlow::build()
    ->subject(Orders::find('ord_48213'))
    ->sendIt();

echo $envelope->receipt()->format(new OneLineFormat);
```

```text
Checkout: On hold: Two or more fraud signals (5 ok, 2 flagged, 5 skipped)
```

To make your format the one `echo $receipt` prints, bind it in a service provider: `$this->app->bind(ReceiptFormat::class, OneLineFormat::class);`.

Two lower-level views sit under the receipt. `$envelope->results()` holds each rule's result value, for checks in code. `$envelope->logs()` holds every raw record: timers, notes, skips and events.

**Next:** [Pass, flag, skip, stop](07-pass-flag-skip-stop.md).
