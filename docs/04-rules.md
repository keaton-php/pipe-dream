<!-- nav-group: Building blocks -->
# Rules and the envelope

A rule is one named class that makes one decision. It receives your object inside an envelope, which also holds the record of the run.

## A rule is a class

```php
<?php

use Keaton\PipeDream\PipeRule;

class CheckCardVelocity extends PipeRule
{
    public static $name = 'Check card velocity';

    public static $description = 'Many cards tried in a few minutes is card testing';

    public function checkCardVelocity($order)
    {
        $tried = $order->cardsTriedRecently;

        if ($tried >= 3) {
            $order->addRiskSignal('Card velocity');
            $this->warning("{$tried} cards tried on this account in 10 minutes");

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}
```

- The method named after the class runs: `CheckCardVelocity` runs `checkCardVelocity()`.
- The first parameter is your subject inside its envelope. It can be named `$subject`, or after what it is, like `$order`. Reads, writes and method calls pass through to your order, so `$order->cardsTriedRecently` reads the model's own field.
- Every other parameter is resolved from Laravel's container. `ConfirmItemsInStock` asks for a `Warehouse` this way. Bind a fake `Warehouse` in a test and the rule uses it.
- `$this->info()`, `$this->warning()`, `$this->error()` and `$this->fatal()` each write a note that is filed under the rule and shown beside it on the receipt. Write the note for the person who will read the receipt.

What the rule returns is what it decided. Here that is `FAIL` with a warning: a concern, and no reason to stop. [Pass, flag, skip, stop](07-pass-flag-skip-stop.md) lists every ending.

## Run one rule alone

A rule does not need the rest of the flow. Put it in a hub by itself and send an order through:

```php
use Keaton\PipeDream\PipeHub;

$envelope = PipeHub::create()
    ->addPipe(CheckCardVelocity::class)
    ->sendIt(Orders::find('ord_48213'));

echo $envelope->receipt();
```

```text
Run · completed with errors (0.04 ms)

  ✗ CheckCardVelocity (0.02 ms)  4 cards tried on this account in 10 minutes
```

A hub without a name is called "Run". `addPipe()` throws a `PipeHubException` for a class that does not exist or is not a `PipeRule`, so a typo cannot drop a step without an error.

## Rule settings

| Setting | What it does |
|---|---|
| `public static $name`, `$description` | Label the rule for your own diagram or admin page. The receipt prints the class name. |
| `public static $runFunction = 'handle';` | Run a method other than the one named after the class. |
| `public static $saveResultAs = 'velocity';` | Also store the rule's result under that key. |
| `protected $errorsAreFatal = true;` | Turn this rule's errors and exceptions into fatals. |

A step can also be a closure: `addPipe(fn ($order) => $order->addRiskSignal('Manual check'))`. Use a closure for a line or two that nothing else reuses. Use a rule when the step has a name your team would say out loud, when a second flow needs it, or when you want to test it alone or inject a service. Closures and rules can share a hub.

## The envelope

`sendIt()` returns the envelope every rule received. `CheckoutFlow::build()` is the checkout from [Your first flow](03-your-first-flow.md), kept in a class that the [checkout walkthrough](12-checkout-walkthrough.md) shows in full:

```php
$order = Orders::find('ord_48213');

$envelope = CheckoutFlow::build()
    ->subject($order)
    ->sendIt();

echo get_class($envelope), PHP_EOL;
echo $envelope->subject() === $order ? 'the same order' : 'a copy', PHP_EOL;
echo $envelope->holdReason, PHP_EOL;
echo $envelope->get(CheckCardVelocity::class), PHP_EOL;
echo $envelope->hasFatalErrors() ? 'stopped' : 'not stopped', PHP_EOL;
```

```text
Keaton\PipeDream\PipeEnvelope
the same order
Two or more fraud signals
2
not stopped
```

- `subject()` is your original object, not a copy.
- `$envelope->holdReason` reads the order's property. Method calls and writes pass through the same way.
- `get(CheckCardVelocity::class)` is the result that rule recorded. `2` is `PipeRule::FAIL`.
- `hasErrors()` and `hasFatalErrors()` say how the run went. An order on hold was not stopped.
- `runId()` is a uuid for the run, and the key a [saved run](09-saving-and-replaying.md) is stored under. Put it in your logs and your support tickets.

## Share a value between steps

Some values belong to the run and not to your object, such as a score built up across stages. `$envelope->set('riskScore', 40)` stores one, and `$envelope->get('riskScore')` reads it in any later step.

Inside a rule, `$this->riskScore = 40` and `$this->riskScore` do the same. The helper pair `$this->setRiskScoreResult(40)` and `$this->getRiskScoreResult()` uses the snake-case key `risk_score`, so pick one style for a value and keep to it.

## Two details

`PipeEnvelope::wrap($order)` makes the envelope before the run, so you can configure it first. Pass the envelope to `subject()` or `sendIt()` in place of the order. [Click intake](11-click-intake.md) does this to save one run.

A property read through the envelope is a copy, so `$envelope->tags[] = 'new'` changes nothing on your object. Assign the whole property back, or call a method on your object.

**Next:** [Hubs and flows](05-hubs-and-flows.md).
