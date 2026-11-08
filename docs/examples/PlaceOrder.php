<?php

use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

class PlaceOrder
{
    public function __invoke(Order $order)
    {
        try {
            $envelope = PipeManager::create([
                PipeHub::create()
                    ->name('Validate the order')
                    ->addPipes([AskForShippingAddress::class, ConfirmItemsInStock::class]),
                PipeHub::create()
                    ->name('Charge')
                    ->addPipes([AuthorizePayment::class, CapturePayment::class]),
            ])
                ->name('Checkout')
                ->subject($order)
                ->sendIt();
        } catch (PipeRuleRedirect $e) {
            return redirect($e->redirectTo);
        }

        if ($envelope->hasFatalErrors()) {
            return response()->json(['error' => $envelope->logger()->fatals()->first()['data']], 422);
        }

        return redirect("/orders/{$order->id}");
    }
}
