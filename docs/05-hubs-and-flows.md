<!-- nav-group: Building blocks -->
# Hubs and flows

A hub is one named stage: a list of rules run in order, and how the stage behaves. A flow is a list of hubs run in order on one subject.

## A stage on its own

This is the checkout's fraud screen, run by itself:

```php
use Keaton\PipeDream\PipeHub;

$envelope = PipeHub::create()
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
        : null)
    ->sendIt(Orders::find('ord_48213'));

echo $envelope->receipt();
```

```text
Fraud screen · On hold: Two or more fraud signals (0.06 ms)

Fraud screen: Does this look like the real cardholder?
  ✗ MatchBillingToShippingCountry (0.02 ms)  Billing US, shipping NL
  ✗ CheckCardVelocity             (0.01 ms)  4 cards tried on this account in 10 minutes
  ✓ ScoreDeviceFingerprint        (0.01 ms)
```

A hub run alone is the whole flow, so its name is also the flow's name. Each line of the hub answers one question. `name()` and `description()` say what the stage is, and both are printed on the receipt. `addPipes()` lists the rules in the order they run. `skipIf()` says when the stage does not apply, and `then()` says what to do once its rules are done.

## Everything a hub does

[Pass, flag, skip, stop](07-pass-flag-skip-stop.md) covers skipping, stopping and the two hooks.

| Method | What it does |
|---|---|
| `name()`, `description()` | Label the stage. |
| `addPipe()`, `addPipes()` | Add rules, as class names, or closures. |
| `skipIf($callback, $reason = null)` | Skip the stage's rules when it does not apply. |
| `skip()` | Skip the whole stage. |
| `then($callback)` | Run after the stage's last rule. |
| `errorsAreFatal()` | Make any failure in this stage stop the flow. |
| `fatalsStopThisHubOnly()` | Let a fatal end this stage only. The next stage runs. |
| `onError()`, `onFatal()` | React to a failure. |

## A flow

`PipeManager::create([...])` takes the hubs in the order they run, as [Your first flow](03-your-first-flow.md) shows. Every hub works on the same envelope, so what one stage leaves, such as the risk signals, is there for the next.

A manager has the same shape as a hub: `name()`, `description()`, `then()`, `onError()`, `onFatal()`, `throwFatals()` and `persistLogs()`. `subject()` sets what the flow is about, and `sendIt()` runs every hub and returns one envelope. The flow's `then()` runs once, after the last hub, and only if nothing stopped the flow. An order that was stopped is therefore not marked placed.

In an app, keep the flow in one class with a `build()` method that returns the manager without a subject. The controller, the queued job and the tests then run the same flow: `CheckoutFlow::build()->subject($order)->sendIt()`. The [checkout walkthrough](12-checkout-walkthrough.md) shows that class.

## Events

A hub can dispatch your Laravel events as it runs, for metrics or anything else that listens. A hub event is constructed with the envelope. A per-rule event also gets the rule's class:

```php
<?php

use Keaton\PipeDream\PipeEnvelope;

class ChargeSkipped
{
    public function __construct(public PipeEnvelope $order) {}
}

class PaymentStepFinished
{
    public function __construct(public PipeEnvelope $order, public string $rule = '') {}
}
```

Name the events on the hub. Here an order on hold and a trusted customer's order go through the Charge stage:

```php
use Illuminate\Support\Facades\Event;
use Keaton\PipeDream\PipeHub;

Event::listen(ChargeSkipped::class, function ($event) {
    echo "Charge skipped for {$event->order->id}", PHP_EOL;
});
Event::listen(PaymentStepFinished::class, function ($event) {
    echo "{$event->rule} finished for {$event->order->id}", PHP_EOL;
});

$charge = PipeHub::create()
    ->name('Charge')
    ->addPipes([
        AuthorizePayment::class,
        CapturePayment::class,
    ])
    ->skipIf(fn ($order) => $order->isOnHold(), 'Order is on hold')
    ->broadcastEventOnSkip(ChargeSkipped::class)
    ->broadcastEventAfterEachPipe(PaymentStepFinished::class);

$held = Orders::find('ord_48213');
$held->holdForReview('Two or more fraud signals');

$charge->sendIt($held);
$charge->sendIt(Orders::find('ord_48214'));
```

```text
Charge skipped for ord_48213
AuthorizePayment finished for ord_48214
CapturePayment finished for ord_48214
```

| Method | Fires |
|---|---|
| `broadcastEventBefore()` | before the hub's first rule |
| `broadcastEventAfter()` | after its last rule, before `then()` |
| `broadcastEventOnSkip()` | in place of both, when the hub is skipped |
| `broadcastEventBeforeEachPipe()` | before each rule runs |
| `broadcastEventAfterEachPipe()` | as each rule hands off to the next |

The order on hold skips the stage, so only the skip event fires. The other order runs both rules. A rule that stops the hub does not hand off, so its after-each event does not fire. A rule that is skipped without running fires neither per-rule event. Every dispatch is written to the run's log.

**Next:** [The receipt](06-the-receipt.md).
