<?php

use Keaton\PipeDream\PipeRule;

class DatacenterIpCheck extends PipeRule
{
    public function datacenterIpCheck($click)
    {
        // Placeholder: your own rule would check the IP against hosting-provider ranges here.
        if (str_starts_with($click->ip, '203.0.113.')) {
            $click->addBotSignal('Hosting provider');
            $this->warning('IP belongs to a hosting provider');

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}
