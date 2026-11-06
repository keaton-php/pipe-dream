<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class MatchPublisherBlacklist extends PipeRule
{
    public function matchPublisherBlacklist($click)
    {
        return self::SUCCESS;
    }
}
