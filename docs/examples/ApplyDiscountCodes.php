<?php

use Keaton\PipeDream\PipeRule;

class ApplyDiscountCodes extends PipeRule
{
    public static $name = 'Apply discount codes';
    public static $description = 'Take any valid code off the subtotal';

    public function applyDiscountCodes($order)
    {
        if ($order->discountCode === null) {
            return $this->skip('No discount code');
        }

        if ($order->discountCode !== 'SPRING20') {
            $this->error("{$order->discountCode} is not a valid code");
        }

        $order->discount = round($order->subtotal() * 0.20, 2);
        $this->info(sprintf('%s applied: -$%.2f', $order->discountCode, $order->discount));

        return self::SUCCESS;
    }
}
