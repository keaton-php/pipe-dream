<?php

$order = new Order(
    id: 'ord_48214',
    items: [
        ['sku' => 'hoodie-gry-l', 'name' => 'Grey hoodie', 'price' => 65.00],
    ],
    shippingAddress: '500 Congress Ave, Austin',
    shippingCountry: 'US',
    billingCountry: 'US',
    trustedCustomer: true,
);

require __DIR__.'/checkout.php';
echo $envelope->receipt();
