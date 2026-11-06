<?php

use Keaton\PipeDream\PipeRule;

class EnforceCampaignCountries extends PipeRule
{
    public function enforceCampaignCountries($click)
    {
        $targets = $click->campaignSettings()->countries;

        if (! in_array($click->country, $targets, true)) {
            $this->fatal("Visitor is in {$click->country}; campaign targets ".implode(', ', $targets));
        }

        return self::SUCCESS;
    }
}
