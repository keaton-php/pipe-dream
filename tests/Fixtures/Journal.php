<?php

namespace R1kon\PipeDream\Tests\Fixtures;

/**
 * Collects what ran, in order, so tests can assert on execution order.
 */
class Journal
{
    public static array $entries = [];

    public static function record(string $entry): void
    {
        static::$entries[] = $entry;
    }

    public static function reset(): void
    {
        static::$entries = [];
    }
}
