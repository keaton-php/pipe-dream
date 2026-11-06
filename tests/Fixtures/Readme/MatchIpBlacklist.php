<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class MatchIpBlacklist extends PipeRule
{
    public function matchIpBlacklist($click)
    {
        if (in_array($click->ip, ['192.0.2.66'], true)) {
            $click->listed = true;

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}
