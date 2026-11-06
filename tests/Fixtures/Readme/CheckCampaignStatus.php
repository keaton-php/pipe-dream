<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class CheckCampaignStatus extends PipeRule
{
    public static $name = 'Check the campaign status';
    public static $description = 'Only live campaigns buy clicks';

    private const CAMPAIGNS = ['spring-sale' => 'live', 'winter-sale' => 'paused', 'demo' => 'test'];

    public function checkCampaignStatus($click)
    {
        $status = self::CAMPAIGNS[$click->campaign] ?? null;

        if ($status === null) {
            $this->fatal("Unknown campaign {$click->campaign}");
        }

        if ($status === 'paused') {
            $this->error("Campaign {$click->campaign} is paused");
        }

        if ($status === 'test') {
            return $this->skip('Test campaign, nothing to check');
        }

        return self::SUCCESS;
    }
}
