<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class RecognizeCampaign extends PipeRule
{
    public function recognizeCampaign($click)
    {
        $campaign = $click->campaign();

        if ($campaign === null) {
            $this->fatal("Unknown campaign {$click->param('campaign')}");
        }

        if ($campaign->paused) {
            $this->fatal("Campaign {$campaign->slug} is paused");
        }

        return self::SUCCESS;
    }
}
