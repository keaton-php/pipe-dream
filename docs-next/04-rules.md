<!-- nav-group: Building blocks -->
# Rules

A rule is one named class that makes one decision. It's the unit you write, test and reuse.

## A rule is a class

<!-- example: docs/examples/CheckCardVelocity.php -->
```php
<?php

use Keaton\PipeDream\PipeRule;

class CheckCardVelocity extends PipeRule
{
    public static $name = 'Check card velocity';
    public static $description = 'Many cards tried in a few minutes is card testing';

    public function checkCardVelocity($order)
    {
        if ($order->cardsTriedRecently >= 3) {
            $order->addRiskSignal('Card velocity');
            $this->warning("{$order->cardsTriedRecently} cards tried on this account in 10 minutes");

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}
```

- **The method named after the class runs.** `CheckCardVelocity` runs `checkCardVelocity()`. Set `public static $runFunction = 'handle';` to use another name.
- **The first parameter is the envelope.** Reads, writes and method calls go straight through to your order, so `$order->cardsTriedRecently` just works.
- **What it returns is what it decided.** Here, `FAIL` with a warning: a concern, but not a reason to stop.

## Run one rule on its own

A rule doesn't need the rest of the flow. Put it in a hub by itself and send an order through it:

<!-- example: docs-next/examples/scripts/rule-alone.php -->
```php
<?php

use Keaton\PipeDream\PipeHub;

$order = new Order(
    id: 'ord_48213',
    items: [['sku' => 'tee-blk-m', 'name' => 'Black tee', 'price' => 30.00]],
    shippingAddress: 'Keizersgracht 123, Amsterdam',
    shippingCountry: 'NL',
    billingCountry: 'US',
    cardsTriedRecently: 4,
);

$envelope = PipeHub::create()
    ->addPipe(CheckCardVelocity::class)
    ->sendIt($order);

echo $envelope->receipt();
```

<!-- output: docs-next/examples/scripts/rule-alone.php -->
```text
Run · completed with errors (0.04 ms)

  ✗ CheckCardVelocity (0.03 ms)  4 cards tried on this account in 10 minutes
```

An unnamed hub's flow is called "Run". The same three lines are the core of [a rule's test](13-testing.md).

## How a rule ends

| The rule... | Write | Recorded as | What happens next |
|---|---|---|---|
| passes | `return self::SUCCESS;` (or return nothing) | ✓ ok | the next rule runs |
| raises a concern | `$this->warning('why'); return self::FAIL;` | ✗ error | the next rule runs |
| fails | `$this->error('why');` | ✗ error | `onError()` hooks run, then the next rule |
| has nothing to do | `return $this->skip('why');` | ↷ skipped | the next rule runs |
| stops the flow | `$this->fatal('why');` | ■ fatal | nothing after it runs |

An unexpected exception is handled like `error()`. `fatal()` is the only way a rule stops things: returning `self::FATAL_FAIL` only records the value. The full set of combinations is on [Outcome tables](18-outcome-tables.md).

## Notes on the receipt

`$this->info()`, `$this->warning()`, `$this->error()` and `$this->fatal()` each write a line filed under the rule. Those lines are the notes you see next to it on the receipt ("4 cards tried on this account in 10 minutes"). Write them for the person reading the receipt, not for yourself.

## Inject what you need

The rule's method is called through Laravel's container. The first parameter gets the envelope; every other parameter is resolved from the container. `ConfirmItemsInStock` asks for a `Warehouse` this way:

```text
public function confirmItemsInStock($order, Warehouse $warehouse)
```

In a test, bind a fake `Warehouse` and the rule uses it.

## Leave a value for later rules

`$this->riskScore = 40` inside a rule stores `riskScore` on the envelope, and `$this->riskScore` reads it back in a later rule (`$this->setRiskScoreResult()` / `getRiskScoreResult()` do the same). Each rule's own result is stored under its class name: `$envelope->get(CheckCardVelocity::class)`.

## Closure or rule?

| Use a closure when | Use a rule when |
|---|---|
| the step is a line or two | the step has a name your team would use |
| nothing else reuses it | more than one flow uses it |
| you don't need to test it alone | you want to test it, or inject services into it |

Closures and rules mix freely in the same hub.

## Rule settings

| Setting | What it does |
|---|---|
| `public static $name`, `$description` | Label the rule. |
| `public static $runFunction = 'handle';` | Run a method other than the one named after the class. |
| `public static $saveResultAs = 'velocity';` | Also store the result under that key. |
| `protected $errorsAreFatal = true;` | Turn this rule's errors and exceptions into fatals. |

`addPipe()` throws a `PipeHubException` for a class that doesn't exist or isn't a `PipeRule`, so a typo never quietly drops a step.

**Next:** [Hubs](05-hubs.md).
