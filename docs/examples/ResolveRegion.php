<?php

use Keaton\PipeDream\PipeRule;

class ResolveRegion extends PipeRule
{
    public static $name = 'Resolve the region';
    public static $description = 'Which part of the world is the visitor in?';

    public function resolveRegion($click, RegionMap $regions)
    {
        $click->set('region', $regions->regionOf($click->country));
        $this->info("Visitor is in {$click->get('region')}");

        return self::SUCCESS;
    }
}
