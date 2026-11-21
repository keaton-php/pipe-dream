<?php

use Keaton\PipeDream\PipeRule;

class ResolveGeoIp extends PipeRule
{
    public function resolveGeoIp($click)
    {
        // Placeholder: your own rule would ask a GeoIP database here.
        $click->location = ['198.51.100.7' => 'US, Austin TX', '203.0.113.23' => 'US, Ashburn VA'][$click->ip] ?? null;

        $click->location === null ? $this->info('Location unknown') : $this->info($click->location);

        return self::SUCCESS;
    }
}
