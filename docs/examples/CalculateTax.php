<?php

use Keaton\PipeDream\PipeRule;

class CalculateTax extends PipeRule
{
    public static $name = 'Calculate tax';
    public static $description = 'Sales tax on what the customer pays';

    public function calculateTax($order)
    {
        $order->tax = round(($order->subtotal() - $order->discount) * 0.0825, 2);
        $this->info(sprintf('Tax: $%.2f', $order->tax));

        return self::SUCCESS;
    }
}
