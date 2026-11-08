<?php

use Keaton\PipeDream\Contracts\ReceiptSummary;

/**
 * The subject of the checkout example: one order, as the customer placed it.
 */
class Order implements ReceiptSummary
{
    public float $discount = 0.0;

    public float $tax = 0.0;

    public array $riskSignals = [];

    public ?string $holdReason = null;

    public bool $charged = false;

    public bool $shipped = false;

    public bool $placed = false;

    public function __construct(
        public string $id,
        public array $items,
        public ?string $shippingAddress,
        public string $shippingCountry,
        public string $billingCountry,
        public ?string $discountCode = null,
        public int $cardsTriedRecently = 1,
        public bool $trustedCustomer = false,
    ) {
    }

    public function subtotal(): float
    {
        return array_sum(array_column($this->items, 'price'));
    }

    public function customerIsTrusted(): bool
    {
        return $this->trustedCustomer;
    }

    public function addRiskSignal(string $reason): void
    {
        $this->riskSignals[] = $reason;
    }

    public function riskSignals(): int
    {
        return count($this->riskSignals);
    }

    public function holdForReview(string $reason): void
    {
        $this->holdReason = $reason;
    }

    public function isOnHold(): bool
    {
        return $this->holdReason !== null;
    }

    public function markPlaced(): void
    {
        $this->placed = true;
    }

    public function receiptSummary(): ?string
    {
        return match (true) {
            $this->isOnHold() => "On hold: {$this->holdReason}",
            $this->placed => 'Placed',
            default => null,
        };
    }
}
