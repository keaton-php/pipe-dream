<?php

$placeOrder = app(PlaceOrder::class);

$orders = [
    new Order(id: 'ord_1', items: [['sku' => 'cap-nvy', 'name' => 'Navy cap', 'price' => 25.00]], shippingAddress: '500 Congress Ave, Austin', shippingCountry: 'US', billingCountry: 'US'),
    new Order(id: 'ord_2', items: [['sku' => 'cap-nvy', 'name' => 'Navy cap', 'price' => 25.00]], shippingAddress: null, shippingCountry: 'US', billingCountry: 'US'),
    new Order(id: 'ord_3', items: [['sku' => 'sold-out', 'name' => 'Limited-run poster', 'price' => 40.00]], shippingAddress: '500 Congress Ave, Austin', shippingCountry: 'US', billingCountry: 'US'),
];

foreach ($orders as $order) {
    $response = $placeOrder($order);
    echo $order->id, ': ', $response->getStatusCode(), ' ', $response->headers->get('Location') ?? $response->getContent(), PHP_EOL;
}
