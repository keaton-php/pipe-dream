<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class EnforceAdvertiserGeoExclusions extends PipeRule
{
    public function enforceAdvertiserGeoExclusions($click)
    {
        return self::SUCCESS;
    }
}
