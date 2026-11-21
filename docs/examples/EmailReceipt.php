<?php

use Keaton\PipeDream\PipeRule;

class EmailReceipt extends PipeRule
{
    public static $name = 'Email the receipt';
    public static $description = 'Tell the customer it is on its way';

    public function emailReceipt($order)
    {
        return self::SUCCESS;
    }
}
