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
