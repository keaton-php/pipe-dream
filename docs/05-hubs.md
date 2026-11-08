<!-- nav-group: Building blocks -->
# Hubs

A hub is one named stage of a flow: a list of rules, run in order, plus how the stage behaves.

## A stage on its own

The checkout's fraud screen, run by itself:

<!-- example: docs/examples/scripts/hub-alone.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;

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

$envelope = PipeHub::create()
    ->name('Fraud screen')
    ->description('Does this look like the real cardholder?')
    ->addPipes([
        MatchBillingToShippingCountry::class,
        CheckCardVelocity::class,
        ScoreDeviceFingerprint::class,
    ])
    ->skipIf(fn ($order) => $order->customerIsTrusted(), 'Trusted customer')
    ->then(fn ($order) => $order->riskSignals() >= 2 ? $order->holdForReview('Two or more fraud signals') : null)
    ->sendIt($order);

echo $envelope->receipt();
```

<!-- output: docs/examples/scripts/hub-alone.php -->
```text
Fraud screen · On hold: Two or more fraud signals (0.07 ms)

Fraud screen — Does this look like the real cardholder?
  ✗ MatchBillingToShippingCountry (0.03 ms)  Billing US, shipping NL
  ✗ CheckCardVelocity             (0.01 ms)  4 cards tried on this account in 10 minutes
  ✓ ScoreDeviceFingerprint        (0.01 ms)
```

A hub run on its own is the whole flow, so its name is the flow's name.

## Reading a hub

Each line of a hub answers one question, so read it top to bottom:

- `name()` and `description()`: what this stage is, and the question it answers. Both show up on the receipt. Write the description as a question ("Does this look like the real cardholder?"), and the receipt reads like an interview.
- `addPipes([...])`: the rules, in the order they run.
- `skipIf(...)`: when this stage doesn't apply.
- `then(...)`: what to do once the stage is done. Here: two or more concerns put the order on hold.

## Skipping a stage

`skipIf()` takes a closure. What it returns decides:

| The closure returns | The hub |
|---|---|
| a non-empty string | is skipped, and the string is the reason |
| `true` | is skipped, with the static reason if you gave one: `skipIf(fn ..., 'Trusted customer')` |
| `false`, `null` or `''` | runs |

So a stage with one reason to skip passes it as the second argument, and a stage with several returns whichever applies. Every rule in a skipped hub is still on the receipt, marked ↷, and the reason is printed once on the hub's line. More on [Pass, flag, skip, stop](09-pass-flag-skip-stop.md).

## Everything a hub does

| Method | What it does |
|---|---|
| `name()`, `description()` | Label the stage. |
| `addPipe()`, `addPipes()` | Add rules (class names) or closures. |
| `skipIf($callback, $reason = null)` | Skip the stage's rules when it doesn't apply. |
| `skip()` | Skip the whole stage, unconditionally. |
| `then($callback)` | Run after the stage's last rule. |
| `errorsAreFatal()` | Make any failure in this stage stop the flow. |
| `fatalsStopThisHubOnly()` | Let a fatal end this stage only; the flow moves on. |
| `onError()`, `onFatal()` | [React to failures](10-reacting-to-failures.md). |
| `broadcastEvent…()` | Dispatch [events](14-events.md) around the stage. |
| `sendIt($subject)` | Run the hub and return the envelope. |

**Next:** [Flows](06-flows.md).
