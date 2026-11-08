<?php

class Warehouse
{
    public function inStock(string $sku): bool
    {
        return $sku !== 'sold-out';
    }
}
