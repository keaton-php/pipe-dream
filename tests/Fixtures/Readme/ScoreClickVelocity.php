<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class ScoreClickVelocity extends PipeRule
{
    public function scoreClickVelocity($click)
    {
        return self::SUCCESS;
    }
}
