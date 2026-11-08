<!-- nav-group: In production -->
# Testing flows

A flow returns its receipt, so a test runs the flow and asserts on what the receipt says. No mocks of the flow itself, and the assertions read like the business rules.

## Test the whole flow

Build the flow in one place ([`CheckoutFlow`](06-flows.md#keep-the-flow-in-a-class)) and use it in every test:

<!-- example: docs/examples/testing/CheckoutFlowTest.php -->
```php
<?php

namespace Tests\Feature;

use CheckoutFlow;
use Keaton\PipeDream\Receipt;
use Order;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    private function order(array $overrides = []): Order
    {
        return new Order(...$overrides + [
            'id' => 'ord_1',
            'items' => [['sku' => 'cap-nvy', 'name' => 'Navy cap', 'price' => 25.00]],
            'shippingAddress' => '500 Congress Ave, Austin',
            'shippingCountry' => 'US',
            'billingCountry' => 'US',
        ]);
    }

    private function checkout(Order $order): Receipt
    {
        return CheckoutFlow::build()->subject($order)->sendIt()->receipt();
    }

    public function test_a_clean_order_is_placed(): void
    {
        $receipt = $this->checkout($this->order());

        $this->assertSame('Placed', $receipt->summary());
        $this->assertSame(Receipt::COMPLETED, $receipt->outcome());
    }

    public function test_two_fraud_signals_hold_the_order_and_nothing_is_charged(): void
    {
        $receipt = $this->checkout($this->order(['billingCountry' => 'CA', 'cardsTriedRecently' => 4]));

        $this->assertSame('On hold: Two or more fraud signals', $receipt->summary());
        $this->assertSame('Held for review: Two or more fraud signals', $receipt->hubs()->firstWhere(fn ($hub) => $hub->name() === 'Charge')->skipReason());
    }

    public function test_a_trusted_customer_skips_the_fraud_screen(): void
    {
        $receipt = $this->checkout($this->order(['trustedCustomer' => true]));

        $this->assertSame('Trusted customer', $receipt->hubs()->firstWhere(fn ($hub) => $hub->name() === 'Fraud screen')->skipReason());
    }

    public function test_an_out_of_stock_item_stops_the_flow_at_validation(): void
    {
        $receipt = $this->checkout($this->order(['items' => [['sku' => 'sold-out', 'name' => 'Poster', 'price' => 40.00]]]));

        $this->assertTrue($receipt->wasStopped());
        $this->assertSame(['Out of stock: Poster'], $receipt->steps()->last()->messages()->all());
        $this->assertSame(['Validate the order'], $receipt->hubs()->map->name()->all());
    }
}
```

These extend your application's own `Tests\TestCase`, and run as they are in PHPUnit or inside a Pest `it()`.

## Test one rule

Run the rule alone in a hub, as on [Rules](04-rules.md#run-one-rule-on-its-own), and assert on its result: `$envelope->get(CheckCardVelocity::class)` is `PipeRule::FAIL`. A rule is resolved through the container, so bind a fake dependency (a `Warehouse` that says everything is sold out) before the run.

## What to assert on

| To check | Assert on |
|---|---|
| the business outcome | `$receipt->summary()` |
| how the run ended | `$receipt->outcome()`, `wasStopped()`, `hasErrors()` |
| that a stage was skipped, and why | `$receipt->hubs()->firstWhere(...)->skipReason()` |
| what a step said | `$step->messages()` |
| which stages ran at all | `$receipt->hubs()->map->name()` |
| one rule's result | `$envelope->get(MyRule::class)`: `SUCCESS`, `FAIL`, `FATAL_FAIL`, `SKIP`, or `null` if it never ran |
| a stop as an exception | `->throwFatals()` on the flow, then `expectException(PipeRuleFatal::class)` |

## No side effects

A flow writes nothing outside your code unless a persist strategy is set or bound. In tests, leave it unbound, or call `->persistLogs(false)` to make it explicit.

**Next:** [Events](14-events.md).
