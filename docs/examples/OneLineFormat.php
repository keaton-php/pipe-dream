<?php

use Keaton\PipeDream\Contracts\ReceiptFormat;
use Keaton\PipeDream\Receipt;

class OneLineFormat implements ReceiptFormat
{
    public function format(Receipt $receipt): string
    {
        $steps = $receipt->steps();

        return sprintf(
            '%s: %s (%d ok, %d flagged, %d skipped)',
            $receipt->flowName(),
            $receipt->summary() ?? $receipt->outcome(),
            $steps->filter->wasOk()->count(),
            $steps->filter->hasErrors()->count(),
            $steps->filter->wasSkipped()->count(),
        );
    }
}
