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
