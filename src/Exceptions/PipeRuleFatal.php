<?php

namespace Keaton\PipeDream\Exceptions;

use Exception;
use Keaton\PipeDream\Concerns\RecordsOrigin;
use Throwable;

/**
 * A rule hit something it cannot continue past. By default a fatal ends the
 * hub it happened in; with stopOnFatal() on that hub it ends the whole flow.
 * It leaves sendIt() only with throwFatals(), and then subject() is the
 * envelope.
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

    public function subject()
    {
        return $this->subject;
    }

    /**
     * Fill in the subject when the fatal was thrown without one (a
     * PipeRuleFatal constructed by hand). A subject already set is kept.
     *
     * @internal
     */
    public function withSubject($subject)
    {
        $this->subject ??= $subject;

        return $this;
    }
}
