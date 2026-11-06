<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

class BudgetLedger
{
    public bool $available = true;

    public array $charges = [];

    public function charge(?string $campaign, float $amount): void
    {
        if (! $this->available) {
            throw new \RuntimeException('Budget service unavailable');
        }

        $this->charges[] = [$campaign, $amount];
    }
}
