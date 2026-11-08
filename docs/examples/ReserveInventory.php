<?php

use Keaton\PipeDream\PipeRule;

class ReserveInventory extends PipeRule
{
    public static $name = 'Reserve inventory';
    public static $description = 'Hold the stock for this order';

    public function reserveInventory($order)
    {
        return self::SUCCESS;
    }
}
