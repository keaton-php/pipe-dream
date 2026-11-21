<?php

use Keaton\PipeDream\Contracts\ReceiptSummary;

/**
 * The subject of the click-intake example: one ad click, as the request described it.
 */
class Click implements ReceiptSummary
{
    public ?string $browser = null;

    public ?string $location = null;

    public ?string $campaignLabel = null;

    public array $botSignals = [];

    public ?string $dropReason = null;

    public bool $settled = false;

    public function __construct(
        public ?string $id,
        public string $userAgent,
        public string $ip,
        public string $campaign,
        public bool $allowlistedPartner = false,
        public int $clicksFromThisDevice = 1,
        public int $clicksInWindow = 0,
    ) {
    }

    public function fromAllowlistedPartner(): bool
    {
        return $this->allowlistedPartner;
    }

    public function addBotSignal(string $reason): void
    {
        $this->botSignals[] = $reason;
    }

    public function botSignals(): int
    {
        return count($this->botSignals);
    }

    public function drop(string $reason): void
    {
        $this->dropReason = $reason;
    }

    public function isDropped(): bool
    {
        return $this->dropReason !== null;
    }

    public function settle(): void
    {
        $this->settled = true;
    }

    public function receiptSummary(): ?string
    {
        return match (true) {
            $this->isDropped() => "Dropped: {$this->dropReason}",
            $this->settled => 'Recorded',
            default => null,
        };
    }
}
