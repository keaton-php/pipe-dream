<?php

use Keaton\PipeDream\PipeRule;

class DeduplicateWithinWindow extends PipeRule
{
    public function deduplicateWithinWindow($click)
    {
        $this->info($click->clicksInWindow === 0 ? 'First click in the window' : 'Repeat click, counted once');

        return self::SUCCESS;
    }
}
