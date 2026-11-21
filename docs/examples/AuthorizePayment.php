<?php

use Keaton\PipeDream\PipeRule;

class AuthorizePayment extends PipeRule
{
    public static $name = 'Authorize the payment';
    public static $description = 'Ask the card network to hold the money';

    public function authorizePayment($order)
    {
        return self::SUCCESS;
    }
}
