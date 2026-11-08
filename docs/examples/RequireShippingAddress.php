<?php

use Keaton\PipeDream\PipeRule;

class RequireShippingAddress extends PipeRule
{
    public static $name = 'Require a shipping address';
    public static $description = 'We cannot ship to nowhere';

    public function requireShippingAddress($order)
    {
        if (blank($order->shippingAddress)) {
            $this->fatal('The order has no shipping address');
        }

        return self::SUCCESS;
    }
}
