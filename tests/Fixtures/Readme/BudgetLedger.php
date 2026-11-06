<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

class BudgetLedger
{
    public function __construct(public bool $available = true)
    {
    }

    public function charge(string $campaign, float $amount): void
    {
        if (! $this->available) {
            throw new \RuntimeException('Budget service unavailable');
        }

        // A real ledger would write to the budget store here.
    }
}
