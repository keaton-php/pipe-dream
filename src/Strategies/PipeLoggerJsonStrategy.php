<?php

namespace Keaton\PipeDream\Strategies;

use Keaton\PipeDream\Contracts\PipeLoggerStrategy;

/**
 * JSON format, the default. Records rebuild as plain arrays; objects in a
 * record are encoded by their public properties (or JsonSerializable).
 */
class PipeLoggerJsonStrategy implements PipeLoggerStrategy
{
    public function __construct(protected int $flags = 0)
    {
    }

    public function output($records)
    {
        return json_encode(
            $records,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION | $this->flags
        );
    }

    public function rebuild($records)
    {
        if ($records === null || $records === '') {
            return [];
        }

        $rebuilt = json_decode($records, true, 512, JSON_THROW_ON_ERROR);

        return is_array($rebuilt) ? $rebuilt : [];
    }
}
