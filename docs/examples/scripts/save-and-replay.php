<?php

use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Models\PipeDreamLog;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;

// Once, in a service provider: save every run.
app()->bind(PipeLoggerPersistStrategy::class, DatabasePersistStrategy::class);

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

$runId = CheckoutFlow::build()->subject($order)->sendIt()->runId();

// Weeks later, from a support ticket:
echo PipeDreamLog::forRun($runId)->first()->receipt();
