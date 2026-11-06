<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class EnforceDailyClickCap extends PipeRule
{
    public function enforceDailyClickCap($click)
    {
        return self::SUCCESS;
    }
}
