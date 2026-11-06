<?php

use Keaton\PipeDream\PipeRule;

class EnforceDailyClickCap extends PipeRule
{
    public function enforceDailyClickCap($click)
    {
        // Placeholder: your own rule would enforce the campaign's daily click cap here.

        return self::SUCCESS;
    }
}
