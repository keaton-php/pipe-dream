<?php

namespace Keaton\PipeDream\Contracts;

/**
 * Optional, for a subject: what the run meant in business terms ("On hold:
 * two fraud signals", "Placed"). A receipt shows it in its header in place
 * of the generic outcome. Return null to keep the generic outcome.
 */
interface ReceiptSummary
{
    public function receiptSummary(): ?string;
}
