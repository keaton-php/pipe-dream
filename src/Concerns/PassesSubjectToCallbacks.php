<?php

namespace R1kon\PipeDream\Concerns;

trait PassesSubjectToCallbacks
{
    /**
     * Callbacks (skipIf, then, onFailure) receive the subject. The container
     * matches parameters by name, so `fn ($subject) => ...` always worked but
     * `fn ($s) => ...` threw. Hand the subject to the closure's first
     * parameter whatever it is called, unless that parameter asks for a
     * class the subject is not an instance of.
     */
    protected function withSubjectParameter($callback, array $parameters): array
    {
        if (! $callback instanceof \Closure || ! array_key_exists('subject', $parameters))
            return $parameters;

        $first = (new \ReflectionFunction($callback))->getParameters()[0] ?? null;

        if (! $first || array_key_exists($first->getName(), $parameters))
            return $parameters;

        $type = $first->getType();
        $acceptsSubject = ! $type instanceof \ReflectionNamedType
            || $type->isBuiltin()
            || $parameters['subject'] instanceof ($type->getName());

        if ($acceptsSubject)
            $parameters[$first->getName()] = $parameters['subject'];

        return $parameters;
    }
}
