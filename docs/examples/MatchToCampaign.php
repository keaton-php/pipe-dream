<?php

use Keaton\PipeDream\PipeRule;

class MatchToCampaign extends PipeRule
{
    public function matchToCampaign($click)
    {
        $click->campaignLabel = ['spring-sale' => 'Spring Sale · Search'][$click->campaign] ?? null;

        if ($click->campaignLabel === null) {
            $this->fatal("Unknown campaign {$click->campaign}");
        }

        $this->info($click->campaignLabel);

        return self::SUCCESS;
    }
}
