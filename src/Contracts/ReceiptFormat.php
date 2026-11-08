<?php

namespace Keaton\PipeDream\Contracts;

use Keaton\PipeDream\Receipt;

/**
 * Turns a run's receipt into output: text for a console, Markdown for a
 * ticket, an array for an API. Pass one to `$envelope->receipt()->format()`,
 * or bind one to this interface to change what `echo $receipt` prints.
 */
interface ReceiptFormat
{
    public function format(Receipt $receipt): mixed;
}
