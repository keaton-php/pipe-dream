<?php

use Keaton\PipeDream\PipeRule;

class AssignLastTouch extends PipeRule
{
    public function assignLastTouch($click)
    {
        $this->info("Credit: {$click->campaignLabel}");

        return self::SUCCESS;
    }
}
