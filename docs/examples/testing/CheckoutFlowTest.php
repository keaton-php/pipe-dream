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
        $this->assertSame(
            'Held for review: Two or more fraud signals',
            $receipt->hubs()->firstWhere(fn ($hub) => $hub->name() === 'Charge')->skipReason(),
        );
    }

    public function test_a_trusted_customer_skips_the_fraud_screen(): void
    {
        $receipt = $this->checkout($this->order(['trustedCustomer' => true]));

        $this->assertSame(
            'Trusted customer',
            $receipt->hubs()->firstWhere(fn ($hub) => $hub->name() === 'Fraud screen')->skipReason(),
        );
    }

    public function test_an_out_of_stock_item_stops_the_flow_at_validation(): void
    {
        $receipt = $this->checkout($this->order([
            'items' => [['sku' => 'sold-out', 'name' => 'Poster', 'price' => 40.00]],
        ]));

        $this->assertTrue($receipt->wasStopped());
        $this->assertSame(['Out of stock: Poster'], $receipt->steps()->last()->messages()->all());
        $this->assertSame(['Validate the order'], $receipt->hubs()->map->name()->all());
    }
}
