<?php

namespace Keaton\PipeDream\Concerns;

trait PassesSubjectToCallbacks
{
    /**
     * Callbacks (skipIf, then, onError, onFatal, closure pipes) and rule methods
     * receive the subject. The container matches parameters by name, so
     * `fn ($subject) => ...` always worked but `fn ($s) => ...` threw. Hand
     * the subject to the first parameter whatever it is called, unless that
     * parameter asks for a class the subject is not an instance of. The
     * onError() / onFatal() hooks likewise hand the exception to the second
     * parameter, so `fn ($post, $fatal) => ...` works too.
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

        $positions = ['subject'];

        if (array_key_exists('exception', $parameters)) {
            $positions[] = 'exception';
        }

        foreach ($positions as $index => $key) {
            $parameter = $reflection->getParameters()[$index] ?? null;

            if (! $parameter || array_key_exists($parameter->getName(), $parameters)) {
                continue;
            }

            if ($this->parameterAccepts($parameter, $parameters[$key])) {
                $parameters[$parameter->getName()] = $parameters[$key];
            }
        }

        return $parameters;
    }

    private function parameterAccepts(\ReflectionParameter $parameter, $value): bool
    {
        $type = $parameter->getType();

        return ! $type instanceof \ReflectionNamedType
            || $type->isBuiltin()
            || $value instanceof ($type->getName());
    }
}
