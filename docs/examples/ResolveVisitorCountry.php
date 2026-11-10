<?php

use Keaton\PipeDream\PipeRule;

class ResolveVisitorCountry extends PipeRule
{
    public function resolveVisitorCountry($click)
    {
        // Most tracking links carry the country. When one doesn't, ask GeoIP:
        // up to three tries, five seconds each.
        $click->country = $click->param('country')
            ?? retry(3, fn (int $attempt) => $this->lookUp($click->ip, $attempt));

        $this->info("Visitor is in {$click->country}");

        return self::SUCCESS;
    }

    protected function lookUp(string $ip, int $attempt): string
    {
        try {
            return GeoIp::country($ip, timeout: 5);
        } catch (RuntimeException $e) {
            $this->warning("GeoIP timed out after 5 s (try {$attempt} of 3)");

            throw $e;
        }
    }
}
