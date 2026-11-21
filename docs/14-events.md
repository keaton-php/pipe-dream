<!-- nav-group: In production -->
# Events

A hub can dispatch your Laravel events as it runs, for metrics, websockets or anything that listens.

## Declare them

Hub events are constructed with the envelope; per-rule events with the envelope and the rule's class:

<!-- example: docs/examples/ChargeSkipped.php -->
```php
<?php

use Keaton\PipeDream\PipeEnvelope;

class ChargeSkipped
{
    public function __construct(public PipeEnvelope $order) {}
}
```

<!-- example: docs/examples/PaymentStepFinished.php -->
```php
<?php

use Keaton\PipeDream\PipeEnvelope;

class PaymentStepFinished
{
    public function __construct(public PipeEnvelope $order, public ?string $rule = null) {}
}
```

## Name them on the hub

<!-- example: docs/examples/scripts/events.php -->
```php
<?php

use Illuminate\Support\Facades\Event;
use Keaton\PipeDream\PipeHub;

Event::listen(ChargeSkipped::class, fn ($event) => print(
    "Charge skipped for {$event->order->id}\n"
));
Event::listen(PaymentStepFinished::class, fn ($event) => print(
    "{$event->rule} finished for {$event->order->id}\n"
));

$chargeHub = fn () => PipeHub::create()
    ->name('Charge')
    ->addPipes([AuthorizePayment::class, CapturePayment::class])
    ->skipIf(fn ($order) => $order->isOnHold() ? "Held for review: {$order->holdReason}" : null)
    ->broadcastEventOnSkip(ChargeSkipped::class)
    ->broadcastEventAfterEachPipe(PaymentStepFinished::class);

$held = new Order(
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
$held->holdForReview('Two or more fraud signals');

$chargeHub()->sendIt($held);
$chargeHub()->sendIt(new Order(
    id: 'ord_48214',
    items: [],
    shippingAddress: '500 Congress Ave, Austin',
    shippingCountry: 'US',
    billingCountry: 'US',
));
```

<!-- output: docs/examples/scripts/events.php -->
```text
Charge skipped for ord_48213
AuthorizePayment finished for ord_48214
CapturePayment finished for ord_48214
```

The held order skips the stage, so only the skip event fires. The other order runs both rules.

## The five points

| Method | Fires |
|---|---|
| `broadcastEventBefore()` | before the hub's first rule |
| `broadcastEventAfter()` | after its last rule, before `then()` |
| `broadcastEventOnSkip()` | instead of both, when the hub is skipped |
| `broadcastEventBeforeEachPipe()` | just before each rule runs |
| `broadcastEventAfterEachPipe()` | as each rule hands off to the next |

A rule that stops the hub never hands off, so its after-each event doesn't fire. Rules skipped without running fire neither per-rule event. Every dispatch is written to the run's log.

**Next:** [Drawing your flow](15-drawing-your-flow.md).
