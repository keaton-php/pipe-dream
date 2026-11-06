<?php

use Keaton\PipeDream\PipeRule;

class RecognizeCampaign extends PipeRule
{
    public function recognizeCampaign($click)
    {
        $campaign = $click->campaignSettings();

        if ($campaign === null) {
            $this->fatal("Unknown campaign {$click->campaign}");
        }

        if ($campaign->paused) {
            $this->fatal("Campaign {$campaign->slug} is paused");
        }

        return self::SUCCESS;
    }
}
