<?php

namespace Keaton\PipeDream\Exceptions;

use Exception;
use Keaton\PipeDream\Concerns\RecordsOrigin;
use Throwable;

/**
 * A rule hit something it cannot continue past. A fatal ends the whole flow
 * (only its own hub, when that hub uses fatalsStopThisHubOnly() inside a
 * manager). It leaves sendIt() only with throwFatals(), and then subject()
 * is the envelope.
 */
class PipeRuleFatal extends Exception
{
    use RecordsOrigin;

    protected $subject;

    public function __construct($message = '', $subject = null, $code = 0, ?Throwable $previous = null)
    {
        $this->subject = $subject;

        parent::__construct($message, $code, $previous);
    }

    protected bool $recorded = false;

    /**
     * Whoever logs this fatal on the envelope marks it, so the hub that
     * catches it records only a fatal nobody has recorded yet.
     *
     * @internal
     */
    public function markRecorded(): static
    {
        $this->recorded = true;

        return $this;
    }

    /** @internal */
    public function wasRecorded(): bool
    {
        return $this->recorded;
    }

    public function subject()
    {
        return $this->subject;
    }

    /**
     * Point the fatal at the flow's envelope before throwFatals() rethrows
     * it, so subject() is always the envelope (and its log), whatever the
     * fatal was constructed with.
     *
     * @internal
     */
    public function withSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }
}
