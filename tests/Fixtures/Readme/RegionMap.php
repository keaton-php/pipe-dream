<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

class RegionMap
{
    public function regionOf(?string $country): string
    {
        return in_array($country, ['CA', 'US', 'MX'], true) ? 'North America' : 'Elsewhere';
    }
}
