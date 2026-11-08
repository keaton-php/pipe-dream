<!-- nav-group: Building blocks -->
# The envelope

Your order travels through the flow inside an envelope. It's what every rule receives and what `sendIt()` returns.

## It's your object, plus the run

<!-- example: docs/examples/scripts/envelope.php -->
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

$envelope = CheckoutFlow::build()->subject($order)->sendIt();

echo get_class($envelope), PHP_EOL;
echo $envelope->subject() === $order ? 'the same $order' : 'a copy', PHP_EOL;
echo $envelope->holdReason, PHP_EOL;                                  // reads go through to the order
echo $envelope->get(CheckCardVelocity::class), PHP_EOL;              // 2 is PipeRule::FAIL
echo $envelope->hasFatalErrors() ? 'stopped' : 'not stopped', PHP_EOL;
```

<!-- output: docs/examples/scripts/envelope.php -->
```text
Keaton\PipeDream\PipeEnvelope
the same $order
Two or more fraud signals
2
not stopped
```

- **Reads, writes and method calls go through to your object.** That's why a rule can write `$order->addRiskSignal(...)` when it was handed an envelope.
- **`subject()`** is your original object, unchanged in identity.
- **`get(Rule::class)`** is the result a rule recorded. `2` is `PipeRule::FAIL`.
- **`hasFatalErrors()`** and **`hasErrors()`** tell you how the run went; held isn't stopped.

## Share values between steps

`$envelope->set('key', $value)` in one step and `$envelope->get('key')` in a later one. Inside a rule, `$this->key = $value` does the same. Use it for things that belong to the run rather than to your object, such as a score built up across stages.

## Every run has an id

`$envelope->runId()` is a uuid for the run. It's the key a [saved run](12-saving-and-replaying.md) is stored under, so put it in your logs, your support tickets and your API responses.

## Wrap it yourself

`PipeEnvelope::wrap($order)` makes the envelope before the run, so you can configure it first, for example `->persistWith(new DatabasePersistStrategy())` to save just this run. Pass the envelope to `subject()` or `sendIt()` in place of the order.

## One gotcha

A property read through the envelope is a copy, so `$envelope->tags[] = 'new'` changes nothing on your object. Assign the whole property back (`$envelope->tags = [...$envelope->tags, 'new'];`) or call a method on your object.

**Next:** [Pass, flag, skip, stop](09-pass-flag-skip-stop.md).
