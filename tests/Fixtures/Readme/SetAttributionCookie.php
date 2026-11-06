<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class SetAttributionCookie extends PipeRule
{
    public function setAttributionCookie($click)
    {
        return self::SUCCESS;
    }
}
