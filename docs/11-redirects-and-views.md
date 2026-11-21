<!-- nav-group: Deciding what happens -->
# Redirects and views

A rule can hand control back to your controller: send the customer somewhere, or show them a page.

## Hand off from a rule

Throw `PipeRuleRedirect` (`::toUrl($url)` or `::toRoute($name)`) or `PipeRuleLoadView` (`::load($view, $data)`). The flow ends and the exception leaves `sendIt()`. A hand-off isn't a failure; the receipt's outcome says `handed off`.

<!-- example: docs/examples/AskForShippingAddress.php -->
```php
<?php

use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeRule;

class AskForShippingAddress extends PipeRule
{
    public static $name = 'Ask for a shipping address';
    public static $description = 'Send the customer back to fill it in';

    public function askForShippingAddress($order)
    {
        if (blank($order->shippingAddress)) {
            PipeRuleRedirect::toUrl("/checkout/{$order->id}/address");
        }

        return self::SUCCESS;
    }
}
```

## In a controller

Catch the hand-offs around `sendIt()`, then check the envelope for a stop:

<!-- example: docs/examples/PlaceOrder.php -->
```php
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
```

Three orders: a good one, one without an address, and one with an item out of stock:

<!-- example: docs/examples/scripts/controller.php -->
```php
<?php

$placeOrder = app(PlaceOrder::class);

$orders = [
    new Order(
        id: 'ord_1',
        items: [['sku' => 'cap-nvy', 'name' => 'Navy cap', 'price' => 25.00]],
        shippingAddress: '500 Congress Ave, Austin',
        shippingCountry: 'US',
        billingCountry: 'US',
    ),
    new Order(
        id: 'ord_2',
        items: [['sku' => 'cap-nvy', 'name' => 'Navy cap', 'price' => 25.00]],
        shippingAddress: null,
        shippingCountry: 'US',
        billingCountry: 'US',
    ),
    new Order(
        id: 'ord_3',
        items: [['sku' => 'sold-out', 'name' => 'Limited-run poster', 'price' => 40.00]],
        shippingAddress: '500 Congress Ave, Austin',
        shippingCountry: 'US',
        billingCountry: 'US',
    ),
];

foreach ($orders as $order) {
    $response = $placeOrder($order);
    echo $order->id, ': ', $response->getStatusCode(), ' ',
        $response->headers->get('Location') ?? $response->getContent(), PHP_EOL;
}
```

<!-- output: docs/examples/scripts/controller.php -->
```text
ord_1: 302 http://localhost/orders/ord_1
ord_2: 302 http://localhost/checkout/ord_2/address
ord_3: 422 {"error":"Out of stock: Limited-run poster"}
```

## Quiet or thrown

By default a fatal is quiet: `sendIt()` returns the envelope and `hasFatalErrors()` is true. Call `throwFatals()` on the flow to get the `PipeRuleFatal` thrown instead, after it's logged and the hooks have run. The exception carries the envelope: `$e->subject()->receipt()`. A fatal contained by `fatalsStopThisHubOnly()` is never thrown.

**Next:** [Saving and replaying runs](12-saving-and-replaying.md).
