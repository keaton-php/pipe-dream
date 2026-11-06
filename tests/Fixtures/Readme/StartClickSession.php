<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class StartClickSession extends PipeRule
{
    public function startClickSession($click)
    {
        $click->set('session', 'sess_'.$click->param('click_id'));

        return self::SUCCESS;
    }
}
