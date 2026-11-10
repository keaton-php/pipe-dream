<?php

/**
 * A stand-in for a GeoIP provider, and it really waits. Each entry in
 * $slowLookups is how long the next lookup takes to answer, in seconds; one
 * slower than the caller's timeout waits out the timeout, then fails. With no
 * entries left, lookups answer at once. The test suite shrinks every wait with
 * $timeScale; the times in the README are from a real run.
 */
class GeoIp
{
    public static array $slowLookups = [];

    public static float $timeScale = 1.0;

    public static function country(string $ip, float $timeout): string
    {
        $answersIn = array_shift(static::$slowLookups) ?? 0.0;

        usleep((int) round(min($answersIn, $timeout) * static::$timeScale * 1_000_000));

        if ($answersIn > $timeout) {
            throw new RuntimeException("GeoIP lookup for {$ip} timed out");
        }

        return 'US';
    }
}
