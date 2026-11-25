<!-- nav-group: Deciding what happens -->
# Redirects and views

A rule can hand control back to your controller, to send the customer somewhere or to show a page. This page also covers how a stop reaches the code that called `sendIt()`.

## Hand off from a rule

Call `PipeRuleRedirect::toUrl($url)`, `PipeRuleRedirect::toRoute($name)` or `PipeRuleLoadView::load($view, $data)`. Each throws, the flow ends, and the exception leaves `sendIt()`. A hand-off is not a failure. The receipt's outcome says `handed off`.

```php
<?php

use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeRule;

class AskForShippingAddress extends PipeRule
{
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

Catch the hand-off around `sendIt()`, then check the envelope for a stop. After a stop, the last step on the receipt is the rule that stopped the flow:

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
                    ->addPipes([
                        AskForShippingAddress::class,
                        ConfirmItemsInStock::class,
                    ]),
                PipeHub::create()
                    ->name('Charge')
                    ->addPipes([
                        AuthorizePayment::class,
                        CapturePayment::class,
                    ]),
            ])
                ->name('Checkout')
                ->subject($order)
                ->sendIt();
        } catch (PipeRuleRedirect $e) {
            return redirect($e->redirectTo);
        }

        if ($envelope->hasFatalErrors()) {
            $stopped = $envelope->receipt()->steps()->last();

            return response()->json(['error' => $stopped->messages()->last()], 422);
        }

        return redirect("/orders/{$order->id}");
    }
}
```

Three orders go through it: a good one, one without an address and one with a sold-out item.

```php
$placeOrder = app(PlaceOrder::class);

$placed = $placeOrder(Orders::find('ord_48214'));
$noAddress = $placeOrder(Orders::find('ord_48216'));
$soldOut = $placeOrder(Orders::find('ord_48215'));

echo $placed->getStatusCode(), ' ', $placed->headers->get('Location'), PHP_EOL;
echo $noAddress->getStatusCode(), ' ', $noAddress->headers->get('Location'), PHP_EOL;
echo $soldOut->getStatusCode(), ' ', $soldOut->getContent(), PHP_EOL;
```

```text
302 http://localhost/orders/ord_48214
302 http://localhost/checkout/ord_48216/address
422 {"error":"Out of stock: Limited-run poster"}
```

A view hand-off is caught the same way: `catch (PipeRuleLoadView $e) { return view($e->view, $e->data); }`. The [click intake](11-click-intake.md) shows one.

## Quiet or thrown

By default a fatal is quiet. `sendIt()` returns the envelope, and `hasFatalErrors()` is true. Call `throwFatals()` on the flow, or on a hub run alone, to have the `PipeRuleFatal` thrown after it is logged, the hooks have run and the run is saved. Inside a flow, a hub's own `throwFatals()` is ignored.

| | Default | With `throwFatals()` |
|---|---|---|
| a fatal stops the flow | `sendIt()` returns, and `hasFatalErrors()` is true | `sendIt()` throws the `PipeRuleFatal` |
| a fatal is contained by `fatalsStopThisHubOnly()` | the flow carries on | the flow carries on, and nothing is thrown |
| a redirect, a view or a configuration error | thrown from `sendIt()` | thrown from `sendIt()` |

The exception carries the envelope, so the receipt is still there:

```php
use Keaton\PipeDream\Exceptions\PipeRuleFatal;

try {
    CheckoutFlow::build()
        ->subject(Orders::find('ord_48215'))
        ->throwFatals()
        ->sendIt();
} catch (PipeRuleFatal $e) {
    echo $e->getMessage(), PHP_EOL;
    echo $e->subject()->receipt()->outcome(), PHP_EOL;
}
```

```text
Out of stock: Limited-run poster
stopped
```

**Next:** [Saving and replaying runs](09-saving-and-replaying.md).
