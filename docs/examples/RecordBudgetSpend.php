<?php

use Keaton\PipeDream\PipeRule;

class RecordBudgetSpend extends PipeRule
{
    public static $name = 'Record the budget spend';
    public static $description = 'Charge the click to the campaign budget';

    public function recordBudgetSpend($click, BudgetLedger $ledger)
    {
        $ledger->charge($click->campaign, 0.40);
        $this->info("Charged {$click->campaign} 0.40");

        return self::SUCCESS;
    }
}
