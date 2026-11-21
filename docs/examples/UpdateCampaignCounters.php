<?php

use Keaton\PipeDream\PipeRule;

class UpdateCampaignCounters extends PipeRule
{
    public function updateCampaignCounters($click)
    {
        // Placeholder: your own rule would bump the campaign's click counter here.
        $this->info('Clicks +1');

        return self::SUCCESS;
    }
}
