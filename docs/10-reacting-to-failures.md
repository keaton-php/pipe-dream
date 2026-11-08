<!-- nav-group: Deciding what happens -->
# Reacting to failures

Run your own code when a step fails: alert someone, release a hold, record a metric.

## Two hooks

Hubs and managers both take them. Each is called with the envelope and the exception:

- `onError(fn ($order, $exception) => ...)` runs for every `error()` and unexpected exception, and then the next step runs.
- `onFatal(fn ($order, $fatal) => ...)` runs when a fatal stops things. `$fatal->getMessage()` is the reason.

A hub's hook hears about its own rules. A manager's hears about every hub's, after the hub's own hook.

## Alert someone, and say so on the record

Put the hook on the flow so it fires once, when the whole flow stops. Inside it, write what you did to the log, so the alert is part of the saved run:

<!-- example: docs/examples/scripts/on-fatal.php -->
```php
<?php

$order = new Order(
    id: 'ord_48215',
    items: [['sku' => 'sold-out', 'name' => 'Limited-run poster', 'price' => 40.00]],
    shippingAddress: '500 Congress Ave, Austin',
    shippingCountry: 'US',
    billingCountry: 'US',
);

$envelope = CheckoutFlow::build()
    ->subject($order)
    ->onFatal(function ($order, $fatal) {
        // send the alert here, then say so on the receipt
        $order->logger()->info('Alert', "Told #merchandising: {$fatal->getMessage()}");
    })
    ->sendIt();

echo $envelope->logger()->infos()->firstWhere('key', 'Alert')['data'];
```

<!-- output: docs/examples/scripts/on-fatal.php -->
```text
Told #merchandising: Out of stock: Limited-run poster
```

Hooks run before `sendIt()` returns, so anything they log is saved with the rest of the run.

## Keep hooks quiet

What a hook throws changes what happens next. A redirect or view leaves `sendIt()`; anything else is recorded as a fatal. Catch your own exceptions inside the hook. The details are on [Outcome tables](18-outcome-tables.md#what-a-hook-may-throw).

**Next:** [Redirects and views](11-redirects-and-views.md).
