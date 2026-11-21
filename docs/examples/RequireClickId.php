<?php

use Keaton\PipeDream\PipeRule;

class RequireClickId extends PipeRule
{
    public function requireClickId($click)
    {
        if ($click->id === null || $click->id === '') {
            $this->fatal('No click id on the request, nothing to trace to an ad');
        }

        return self::SUCCESS;
    }
}
