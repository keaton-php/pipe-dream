<?php

namespace Keaton\PipeDream\Strategies;

use Keaton\PipeDream\Contracts\PipeLoggerStrategy;

/**
 * PHP serialize() format. Rebuilding never instantiates classes: any object
 * inside a record comes back as __PHP_Incomplete_Class, so a tampered log
 * cannot trigger object injection.
 */
class PipeLoggerSerializeStrategy implements PipeLoggerStrategy
{
    public function output($records)
    {
        return serialize($records);
    }

    public function rebuild($records)
    {
        if ($records === null || $records === '') {
            return [];
        }

        $rebuilt = unserialize($records, ['allowed_classes' => false]);

        return is_array($rebuilt) ? $rebuilt : [];
    }
}
