<?php

use Keaton\PipeDream\PipeRule;

class ScoreClickVelocity extends PipeRule
{
    public function scoreClickVelocity($click)
    {
        // Placeholder: your own rule would score how fast clicks arrive from this visitor here.

        return self::SUCCESS;
    }
}
