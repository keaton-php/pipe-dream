<?php

use Keaton\PipeDream\PipeRule;

class CheckCardVelocity extends PipeRule
{
    public static $name = 'Check card velocity';
    public static $description = 'Many cards tried in a few minutes is card testing';

    public function checkCardVelocity($order)
    {
        if ($order->cardsTriedRecently >= 3) {
            $order->addRiskSignal('Card velocity');
            $this->warning("{$order->cardsTriedRecently} cards tried on this account in 10 minutes");

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}
