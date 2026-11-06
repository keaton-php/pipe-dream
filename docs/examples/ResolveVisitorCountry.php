<?php

use Keaton\PipeDream\PipeRule;

class ResolveVisitorCountry extends PipeRule
{
    public function resolveVisitorCountry($click)
    {
        $click->country = $click->param('country') ?? 'US';
        $this->info("Visitor is in {$click->country}");

        return self::SUCCESS;
    }
}
