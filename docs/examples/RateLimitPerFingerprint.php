<?php

use Keaton\PipeDream\PipeRule;

class RateLimitPerFingerprint extends PipeRule
{
    public function rateLimitPerFingerprint($click)
    {
        if ($click->clicksFromThisDevice > 5) {
            $click->addBotSignal('Click rate');
            $this->warning("{$click->clicksFromThisDevice} clicks from this device in a minute");

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}
