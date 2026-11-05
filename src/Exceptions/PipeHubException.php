<?php

namespace R1kon\PipeDream\Exceptions;

use Exception;

/**
 * A flow was built or run incorrectly: a pipe that is not a rule, a hub that
 * is not a hub, a hub run without a subject.
 */
class PipeHubException extends Exception
{
    public static function invalidPipe($pipe): static
    {
        if (is_string($pipe) && ! class_exists($pipe)) {
            return new static("Pipe [{$pipe}] does not exist. Pipes must be PipeRule class names or closures.");
        }

        $type = is_string($pipe) ? $pipe : get_debug_type($pipe);

        return new static("Pipe [{$type}] is not a PipeRule. Pipes must be PipeRule class names or closures.");
    }

    public static function invalidHub($hub): static
    {
        return new static('PipeManager hubs must be PipeHub instances, got ['.get_debug_type($hub).'].');
    }

    public static function missingSubject(): static
    {
        return new static('No subject provided: call subject() or pass one to sendIt().');
    }

    public static function invalidEvent($event): static
    {
        $name = is_string($event) ? $event : get_debug_type($event);

        return new static("Event [{$name}] is not a class that exists.");
    }
}
