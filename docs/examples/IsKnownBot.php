<?php

use Keaton\PipeDream\PipeRule;

class IsKnownBot extends PipeRule
{
    public function isKnownBot($click)
    {
        if (preg_match('/bot|crawler|spider|curl|python-requests/i', $click->userAgent)) {
            $click->addBotSignal('Scripted client');
            $this->warning('User agent matches a scripted client');

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}
