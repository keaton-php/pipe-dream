<?php

use Keaton\PipeDream\PipeRule;

class MatchPublisherBlacklist extends PipeRule
{
    public function matchPublisherBlacklist($click)
    {
        // Placeholder: your own rule would check the publisher against your blacklist here.

        return self::SUCCESS;
    }
}
