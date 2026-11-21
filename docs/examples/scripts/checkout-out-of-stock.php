<?php

$order = new Order(
    id: 'ord_48215',
    items: [
        ['sku' => 'tee-blk-m', 'name' => 'Black tee', 'price' => 30.00],
        ['sku' => 'sold-out', 'name' => 'Limited-run poster', 'price' => 40.00],
    ],
    shippingAddress: '500 Congress Ave, Austin',
    shippingCountry: 'US',
    billingCountry: 'US',
);

require __DIR__.'/checkout.php';
echo $envelope->receipt();
