<?php

namespace Keaton\PipeDream\Exceptions;

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

    public static function sharedRuleNotCloneable(string $rule, ?\Throwable $previous = null): static
    {
        return new static(
            "Rule [{$rule}] is bound as shared (singleton or instance) but cannot be copied for a run. "
            .'Bind it non-shared, e.g. $app->bind('.class_basename($rule).'::class), or make it cloneable.',
            0,
            $previous
        );
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
