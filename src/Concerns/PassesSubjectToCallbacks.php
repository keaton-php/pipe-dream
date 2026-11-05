<?php

namespace R1kon\PipeDream\Concerns;

trait PassesSubjectToCallbacks
{
    /**
     * Callbacks (skipIf, then, onFailure, closure pipes) and rule methods
     * receive the subject. The container matches parameters by name, so
     * `fn ($subject) => ...` always worked but `fn ($s) => ...` threw. Hand
     * the subject to the first parameter whatever it is called, unless that
     * parameter asks for a class the subject is not an instance of.
     */
    protected function withSubjectParameter($callback, array $parameters): array
    {
        if (! array_key_exists('subject', $parameters)) {
            return $parameters;
        }

        if ($callback instanceof \Closure) {
            $reflection = new \ReflectionFunction($callback);
        } elseif (is_array($callback) && count($callback) === 2 && method_exists($callback[0], $callback[1])) {
            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
        } else {
            return $parameters;
        }

        $first = $reflection->getParameters()[0] ?? null;

        if (! $first || array_key_exists($first->getName(), $parameters)) {
            return $parameters;
        }

        $type = $first->getType();
        $acceptsSubject = ! $type instanceof \ReflectionNamedType
            || $type->isBuiltin()
            || $parameters['subject'] instanceof ($type->getName());

        if ($acceptsSubject) {
            $parameters[$first->getName()] = $parameters['subject'];
        }

        return $parameters;
    }
}
