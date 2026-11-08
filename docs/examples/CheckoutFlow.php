<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

class CheckoutFlow
{
    public static function build(): PipeManager
    {
        return PipeManager::create([

            PipeHub::create()
                ->name('Validate the order')
                ->description('Is this a complete order for items we can actually ship?')
                ->addPipes([
                    RequireShippingAddress::class,
                    ConfirmItemsInStock::class,
                    ApplyDiscountCodes::class,
                    CalculateTax::class,
                ]),

            PipeHub::create()
                ->name('Fraud screen')
                ->description('Does this look like the real cardholder?')
                ->addPipes([
                    MatchBillingToShippingCountry::class,
                    CheckCardVelocity::class,
                    ScoreDeviceFingerprint::class,
                ])
                ->skipIf(fn ($order) => $order->customerIsTrusted(), 'Trusted customer')
                ->then(fn ($order) => $order->riskSignals() >= 2 ? $order->holdForReview('Two or more fraud signals') : null),

            PipeHub::create()
                ->name('Charge')
                ->description('Take the money, only for orders we will ship')
                ->addPipes([
                    AuthorizePayment::class,
                    CapturePayment::class,
                ])
                ->skipIf(fn ($order) => $order->isOnHold() ? "Held for review: {$order->holdReason}" : null)
                ->errorsAreFatal(),

            PipeHub::create()
                ->name('Fulfil')
                ->description('Reserve stock, tell the warehouse, email the receipt')
                ->addPipes([
                    ReserveInventory::class,
                    SendToWarehouse::class,
                    EmailReceipt::class,
                ])
                ->skipIf(fn ($order) => $order->isOnHold() ? "Held for review: {$order->holdReason}" : null),

        ])
            ->name('Checkout')
            ->then(fn ($order) => $order->markPlaced());
    }
}
