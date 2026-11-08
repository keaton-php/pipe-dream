<?php

namespace Keaton\PipeDream;

use Illuminate\Support\Collection;

/**
 * One step of a run: a rule (or closure pipe) that executed or was skipped.
 *
 * A StepRecord is computed from the logger's records, not stored, so it reads
 * the same whether the run just happened or was rebuilt from the database.
 * `$envelope->steps()` returns these in run order; hub-level log lines (a
 * hub's own "Sending subject through all pipes", a manager's bookkeeping) are
 * not steps and never appear here.
 */
class StepRecord
{
    /**
     * @param  string  $key  the key the step logged under (a rule's FQCN, or a closure's "Closure@file:line")
     * @param  string|null  $hub  the hub the step ran in, when the flow named its hubs
     * @param  mixed  $result  what the step returned (a result constant, or a custom value)
     * @param  float|null  $duration  how long the step took, in milliseconds (null when timers are off)
     * @param  array<int, string>  $messages  the info/warning/error lines the step logged, in order
     */
    public function __construct(
        protected string $key,
        protected ?string $hub,
        protected mixed $result,
        protected ?float $duration,
        protected array $messages = []
    ) {
    }

    /**
     * A short name for the step: the rule's class basename, or the closure's
     * label ("Closure@file:line").
     */
    public function name(): string
    {
        return class_basename($this->key);
    }

    /**
     * The full key the step logged under: a rule's fully-qualified class name,
     * or a closure's "Closure@file:line".
     */
    public function rule(): string
    {
        return $this->key;
    }

    /**
     * The hub the step ran in, or null when the flow did not name its hubs.
     */
    public function hub(): ?string
    {
        return $this->hub;
    }

    /**
     * How long the step took, in milliseconds, or null when the run was logged
     * without timers (`PipeLogger::LEVEL_NO_TIMERS`).
     */
    public function duration(): ?float
    {
        return $this->duration;
    }

    /**
     * What the step returned: a `PipeRule` result constant (SUCCESS, FAIL,
     * FATAL_FAIL, SKIP) or whatever custom value the rule returned.
     */
    public function returnValue(): mixed
    {
        return $this->result;
    }

    /**
     * The step's outcome as a word: 'ok', 'skipped', 'error' or 'fatal'. Any
     * return value that is not a failure or a skip reads as 'ok'.
     */
    public function status(): string
    {
        return match ($this->result) {
            PipeRule::FAIL => 'error',
            PipeRule::FATAL_FAIL => 'fatal',
            PipeRule::SKIP => 'skipped',
            default => 'ok',
        };
    }

    public function wasOk(): bool
    {
        return $this->status() === 'ok';
    }

    public function wasSkipped(): bool
    {
        return $this->status() === 'skipped';
    }

    public function hasErrors(): bool
    {
        return $this->status() === 'error';
    }

    public function wasFatal(): bool
    {
        return $this->status() === 'fatal';
    }

    /**
     * Why the step was skipped: the first line it logged, when it was skipped.
     * Null for a step that ran, or a skip that gave no reason.
     */
    public function skipReason(): ?string
    {
        return $this->wasSkipped() ? ($this->messages[0] ?? null) : null;
    }

    /**
     * The info, warning and error lines the step logged, in order. A Collection
     * so you can map, filter and implode them. Hub-level lines are not here.
     *
     * @return Collection<int, string>
     */
    public function messages(): Collection
    {
        return collect($this->messages);
    }
}
