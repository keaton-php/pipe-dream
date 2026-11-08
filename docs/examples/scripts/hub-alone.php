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
