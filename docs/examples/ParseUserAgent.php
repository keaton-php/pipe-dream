<?php

use Keaton\PipeDream\PipeRule;

class ParseUserAgent extends PipeRule
{
    public function parseUserAgent($click)
    {
        preg_match('/(Chrome|Firefox|Safari)\/(\d+)/', $click->userAgent, $browser);
        $os = str_contains($click->userAgent, 'Windows') ? 'Windows' : (str_contains($click->userAgent, 'Mac') ? 'macOS' : 'Linux');

        $click->browser = ($browser[1] ?? 'Unknown browser').' '.($browser[2] ?? '').' on '.$os;
        $this->info(str_replace('  ', ' ', $click->browser));

        return self::SUCCESS;
    }
}
