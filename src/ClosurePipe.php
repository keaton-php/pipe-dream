<?php

namespace Keaton\PipeDream;

use Closure;
use ReflectionFunction;

/**
 * A closure added with PipeHub::addPipe(fn ($subject) => ...). It runs like
 * a rule: timed, logged and recorded under a key that points at where it
 * was written ("Closure@ClickFlow.php:42"), then the chain continues.
 * Its return value is its result (SUCCESS when it returns nothing or
 * returns the subject).
 */
class ClosurePipe extends PipeRule
{
    public static $name = 'Closure';
    public static $description = 'An inline closure pipe';

    protected Closure $callback;

    protected string $key;

    public function __construct(Closure $callback, ?string $key = null)
    {
        parent::__construct();

        $this->callback = $callback;
        $this->key = $key ?? static::keyFor($callback);
    }

    public static function keyFor(Closure $callback): string
    {
        $reflection = new ReflectionFunction($callback);

        if (! $file = $reflection->getFileName()) {
            return 'Closure';
        }

        return sprintf('Closure@%s:%d', basename($file), $reflection->getStartLine());
    }

    public function ruleKey(): string
    {
        return $this->key;
    }

    public function callback(): Closure
    {
        return $this->callback;
    }

    public function execute($subject)
    {
        $this->skippedItself = false;

        $result = $this->container()->call(
            $this->callback,
            $this->withSubjectParameter($this->callback, ['subject' => $subject])
        ) ?? $this->defaultResult;

        // A closure that hands the subject back (the old pipe style) succeeded.
        if ($result === $subject || ($subject instanceof PipeEnvelope && $result === $subject->subject())) {
            $result = $this->defaultResult;
        }

        $this->logExecutionResult($subject, $this->ruleKey(), $result);

        return $subject;
    }
}
