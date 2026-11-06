<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Keaton\PipeDream\PipeRule;

class RequireTrackingParameters extends PipeRule
{
    public function requireTrackingParameters($click)
    {
        foreach (['click_id', 'campaign', 'pub'] as $key) {
            if ($click->param($key) === null) {
                $this->fatal("Missing required parameter: {$key}");
            }
        }

        return self::SUCCESS;
    }
}
