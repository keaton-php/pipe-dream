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
