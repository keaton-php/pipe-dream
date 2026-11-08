<?php

$order = new Order(
    id: 'ord_48215',
    items: [['sku' => 'sold-out', 'name' => 'Limited-run poster', 'price' => 40.00]],
    shippingAddress: '500 Congress Ave, Austin',
    shippingCountry: 'US',
    billingCountry: 'US',
);

$envelope = CheckoutFlow::build()
    ->subject($order)
    ->onFatal(function ($order, $fatal) {
        // send the alert here, then say so on the receipt
        $order->logger()->info('Alert', "Told #merchandising: {$fatal->getMessage()}");
    })
    ->sendIt();

echo $envelope->logger()->infos()->firstWhere('key', 'Alert')['data'];
