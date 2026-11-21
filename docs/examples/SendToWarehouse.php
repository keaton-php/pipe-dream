<?php

use Keaton\PipeDream\PipeRule;

class SendToWarehouse extends PipeRule
{
    public static $name = 'Send to the warehouse';
    public static $description = 'Queue the order for picking and packing';

    public function sendToWarehouse($order)
    {
        $order->shipped = true;

        return self::SUCCESS;
    }
}
