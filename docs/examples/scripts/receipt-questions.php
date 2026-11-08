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

$receipt = CheckoutFlow::build()->subject($order)->sendIt()->receipt();

echo $receipt->outcome(), PHP_EOL;                                            // how it ended
echo $receipt->summary(), PHP_EOL;                                            // what the order says
echo $receipt->steps()->first(fn ($step) => $step->hasErrors())->name(), PHP_EOL;  // the first concern
echo $receipt->hubs()->filter->skipReason()->map->name()->implode(', '), PHP_EOL;  // what never ran
