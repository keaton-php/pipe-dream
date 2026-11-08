<?php

use Illuminate\Support\Str;
use Keaton\PipeDream\PipeRule;

class ConfirmItemsInStock extends PipeRule
{
    public static $name = 'Confirm items in stock';
    public static $description = 'Every item in the cart can actually ship';

    public function confirmItemsInStock($order, Warehouse $warehouse)
    {
        $missing = array_filter($order->items, fn ($item) => ! $warehouse->inStock($item['sku']));

        if ($missing !== []) {
            $this->fatal('Out of stock: '.implode(', ', array_column($missing, 'name')));
        }

        $this->info(count($order->items).' '.Str::plural('item', count($order->items)).' in stock');

        return self::SUCCESS;
    }
}
