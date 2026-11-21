<?php

use Keaton\PipeDream\PipeRule;

class CapturePayment extends PipeRule
{
    public static $name = 'Capture the payment';
    public static $description = 'Take the money we were authorized for';

    public function capturePayment($order)
    {
        $order->charged = true;

        return self::SUCCESS;
    }
}
