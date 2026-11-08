<?php

use Illuminate\Support\Facades\Event;
use Keaton\PipeDream\PipeHub;

Event::listen(ChargeSkipped::class, fn ($event) => print("Charge skipped for {$event->order->id}\n"));
Event::listen(PaymentStepFinished::class, fn ($event) => print("{$event->rule} finished for {$event->order->id}\n"));

$chargeHub = fn () => PipeHub::create()
    ->name('Charge')
    ->addPipes([AuthorizePayment::class, CapturePayment::class])
    ->skipIf(fn ($order) => $order->isOnHold() ? "Held for review: {$order->holdReason}" : null)
    ->broadcastEventOnSkip(ChargeSkipped::class)
    ->broadcastEventAfterEachPipe(PaymentStepFinished::class);

$held = new Order(
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
$held->holdForReview('Two or more fraud signals');

$chargeHub()->sendIt($held);
$chargeHub()->sendIt(new Order(id: 'ord_48214', items: [], shippingAddress: '500 Congress Ave, Austin', shippingCountry: 'US', billingCountry: 'US'));
