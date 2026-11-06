<?php

use Keaton\PipeDream\PipeRule;

class StartClickSession extends PipeRule
{
    public function startClickSession($click)
    {
        $click->set('session', 'sess_'.$click->id);

        return self::SUCCESS;
    }
}
