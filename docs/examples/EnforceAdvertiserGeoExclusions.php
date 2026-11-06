<?php

use Keaton\PipeDream\PipeRule;

class EnforceAdvertiserGeoExclusions extends PipeRule
{
    public function enforceAdvertiserGeoExclusions($click)
    {
        // Placeholder: your own rule would apply the advertiser's own geo exclusions here.

        return self::SUCCESS;
    }
}
