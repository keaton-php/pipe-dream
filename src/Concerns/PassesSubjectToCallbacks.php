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

        $declared = $reflection->getParameters();

        foreach ($positions as $index => $key) {
            $parameter = $declared[$index] ?? null;

            if (! $parameter || $parameter->isVariadic() || array_key_exists($parameter->getName(), $parameters)) {
                continue;
            }

            $value = $parameters[$key];

            // A hook's second parameter typed as some exception class always
            // gets the real exception, so PHP reports a mismatch instead of
            // the container quietly building a fresh, unrelated exception.
            $isExceptionSlot = $key === 'exception' && $this->namesAThrowable($parameter->getType());

            if ($isExceptionSlot || $this->typeAccepts($parameter->getType(), $value)) {
                $parameters[$parameter->getName()] = $value;
                unset($parameters[$key]);
            }
        }

        return $parameters;
    }

    private function typeAccepts(?\ReflectionType $type, $value): bool
    {
        if ($type === null) {
            return true;
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if ($this->typeAccepts($member, $value)) {
                    return true;
                }
            }

            return false;
        }

        if ($type instanceof \ReflectionIntersectionType) {
            foreach ($type->getTypes() as $member) {
                if (! $this->typeAccepts($member, $value)) {
                    return false;
                }
            }

            return true;
        }

        if (! $type instanceof \ReflectionNamedType) {
            return false;
        }

        $name = $type->getName();

        if (! $type->isBuiltin()) {
            return $value instanceof $name;
        }

        return match ($name) {
            'mixed' => true,
            'object' => is_object($value),
            'iterable' => is_iterable($value),
            'callable' => is_callable($value),
            'array' => is_array($value),
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value),
            'bool' => is_bool($value),
            'false' => $value === false,
            'true' => $value === true,
            'null' => $value === null,
            default => false,
        };
    }

    private function namesAThrowable(?\ReflectionType $type): bool
    {
        $types = $type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType
            ? $type->getTypes()
            : [$type];

        foreach ($types as $member) {
            if ($member instanceof \ReflectionNamedType && ! $member->isBuiltin()
                && (is_a($member->getName(), \Throwable::class, true))) {
                return true;
            }
        }

        return false;
    }
}
