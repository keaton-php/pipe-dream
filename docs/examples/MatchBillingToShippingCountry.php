<?php

use Keaton\PipeDream\PipeRule;

class MatchBillingToShippingCountry extends PipeRule
{
    public static $name = 'Match billing to shipping country';
    public static $description = 'A card from one country shipping to another is a signal';

    public function matchBillingToShippingCountry($order)
    {
        if ($order->billingCountry !== $order->shippingCountry) {
            $order->addRiskSignal('Country mismatch');
            $this->warning("Billing {$order->billingCountry}, shipping {$order->shippingCountry}");

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}
