<?php

namespace Keaton\PipeDream\Support;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Throwable;

/**
 * Turns any value into one that json_encode() always accepts, so a log can
 * always be persisted: a rule may return a resource, a recursive object or
 * INF and the flow's outcome is still recorded.
 *
 * - null, booleans, integers, finite floats and strings are kept (invalid
 *   UTF-8 bytes in a string are replaced);
 * - NAN, INF and -INF become the strings "NAN", "INF" and "-INF";
 * - resources become "resource(stream)" (or "resource (closed)");
 * - arrays are normalised recursively, up to MAX_DEPTH levels;
 * - closures become "Closure"; enums their value (or name); dates an
 *   ISO 8601 string; exceptions their class and message;
 * - JsonSerializable, Arrayable and Stringable objects use what they
 *   provide; any other object becomes its public properties plus a
 *   "__class" key;
 * - an object already being normalised (a cycle) becomes "[recursion: Class]".
 */
final class LogValue
{
    public const MAX_DEPTH = 16;

    public static function normalize($value)
    {
        $seen = [];

        return self::walk($value, 0, $seen);
    }

    private static function walk($value, int $depth, array &$seen)
    {
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        if (is_float($value)) {
            if (is_nan($value)) {
                return 'NAN';
            }

            return is_infinite($value) ? ($value > 0 ? 'INF' : '-INF') : $value;
        }

        if (is_resource($value)) {
            return 'resource('.get_resource_type($value).')';
        }

        if (gettype($value) === 'resource (closed)') {
            return 'resource (closed)';
        }

        if ($depth >= self::MAX_DEPTH) {
            return '[max depth]';
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = self::walk($item, $depth + 1, $seen);
            }

            return $normalized;
        }

        if (is_object($value)) {
            return self::object($value, $depth, $seen);
        }

        return '['.gettype($value).']';
    }

    private static function object(object $value, int $depth, array &$seen)
    {
        $class = get_class($value);
        $id = spl_object_id($value);

        if (isset($seen[$id])) {
            return "[recursion: {$class}]";
        }

        if ($value instanceof Closure) {
            return 'Closure';
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof Throwable) {
            return ['exception' => $class, 'message' => $value->getMessage()];
        }

        $seen[$id] = true;

        try {
            if ($value instanceof JsonSerializable) {
                return self::walk($value->jsonSerialize(), $depth + 1, $seen);
            }

            if ($value instanceof Arrayable) {
                return self::walk($value->toArray(), $depth + 1, $seen);
            }

            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            $properties = self::walk(get_object_vars($value), $depth + 1, $seen);

            return is_array($properties) ? ['__class' => $class] + $properties : ['__class' => $class];
        } catch (Throwable $e) {
            return "[unloggable: {$class}]";
        } finally {
            unset($seen[$id]);
        }
    }
}
