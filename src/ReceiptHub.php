<?php

namespace Keaton\PipeDream;

use Illuminate\Support\Collection;

/**
 * One hub on a receipt: its name, description and the steps it ran.
 */
class ReceiptHub
{
    /**
     * @param  Collection<int, StepRecord>  $steps
     */
    public function __construct(
        protected ?string $name,
        protected ?string $description,
        protected Collection $steps,
        protected bool $skipped = false
    ) {
    }

    /**
     * The hub's name, or null for steps that ran outside a named hub.
     */
    public function name(): ?string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * @return Collection<int, StepRecord>
     */
    public function steps(): Collection
    {
        return $this->steps;
    }

    /**
     * Whether the whole hub was skipped with skip(): nothing in it ran.
     */
    public function wasSkipped(): bool
    {
        return $this->skipped;
    }

    /**
     * The reason every rule in the hub was skipped, when they all were for
     * the same reason (as with skipIf()). Null otherwise.
     */
    public function skipReason(): ?string
    {
        if ($this->steps->isEmpty() || $this->steps->contains(fn (StepRecord $step) => ! $step->wasSkipped())) {
            return null;
        }

        $reasons = $this->steps->map(fn (StepRecord $step) => $step->skipReason())->unique();

        return $reasons->count() === 1 ? $reasons->first() : null;
    }

    /**
     * The hub's total time in milliseconds, or null when the run was logged
     * without timers.
     */
    public function duration(): ?float
    {
        $timed = $this->steps->map(fn (StepRecord $step) => $step->duration())->filter(fn ($duration) => $duration !== null);

        return $timed->isEmpty() ? null : (float) $timed->sum();
    }
}
