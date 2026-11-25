<?php

/*
 * The classes the checkout examples in the README and the docs pages use but do not show.
 *
 * They are in the global namespace because the pages show them that way. The docs runner
 * (tests/Support/DocsRunner.php) loads this file before it runs the PHP blocks of the pages.
 */

use Keaton\PipeDream\Contracts\ReceiptSummary;
use Keaton\PipeDream\PipeRule;

/**
 * The subject of the checkout examples: one order, as the customer placed it.
 */
class Order implements ReceiptSummary
{
    public float $discount = 0.0;

    public float $tax = 0.0;

    /** @var list<string> */
    public array $riskSignals = [];

    public ?string $holdReason = null;

    public bool $charged = false;

    public bool $shipped = false;

    public bool $placed = false;

    /**
     * @param  list<array{sku: string, name: string, price: float}>  $items
     */
    public function __construct(
        public string $id,
        public array $items,
        public ?string $shippingAddress,
        public string $shippingCountry,
        public string $billingCountry,
        public ?string $discountCode = null,
        public int $cardsTriedRecently = 1,
        public bool $trustedCustomer = false,
    ) {}

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

    /** The headline of the receipt, in the words of the business. */
    public function receiptSummary(): ?string
    {
        return match (true) {
            $this->isOnHold() => "On hold: {$this->holdReason}",
            $this->placed => 'Placed',
            default => null,
        };
    }
}

/**
 * The four sample orders the pages send through the checkout. Every call builds a new order.
 */
class Orders
{
    public static function find(string $id): Order
    {
        $austin = '500 Congress Ave, Austin';

        return match ($id) {
            // Shipping to another country than the card, and four cards tried in ten minutes.
            'ord_48213' => new Order(
                id: 'ord_48213',
                items: [
                    ['sku' => 'tee-blk-m', 'name' => 'Black tee', 'price' => 30.00],
                    ['sku' => 'hoodie-gry-l', 'name' => 'Gray hoodie', 'price' => 65.00],
                    ['sku' => 'cap-nvy', 'name' => 'Navy cap', 'price' => 25.00],
                ],
                shippingAddress: 'Keizersgracht 123, Amsterdam',
                shippingCountry: 'NL',
                billingCountry: 'US',
                discountCode: 'SPRING20',
                cardsTriedRecently: 4,
            ),
            // A customer the shop trusts.
            'ord_48214' => new Order(
                id: 'ord_48214',
                items: [['sku' => 'hoodie-gry-l', 'name' => 'Gray hoodie', 'price' => 65.00]],
                shippingAddress: $austin,
                shippingCountry: 'US',
                billingCountry: 'US',
                trustedCustomer: true,
            ),
            // One of the two items is sold out.
            'ord_48215' => new Order(
                id: 'ord_48215',
                items: [
                    ['sku' => 'tee-blk-m', 'name' => 'Black tee', 'price' => 30.00],
                    ['sku' => 'sold-out', 'name' => 'Limited-run poster', 'price' => 40.00],
                ],
                shippingAddress: $austin,
                shippingCountry: 'US',
                billingCountry: 'US',
            ),
            // The customer has not given a shipping address yet.
            'ord_48216' => new Order(
                id: 'ord_48216',
                items: [['sku' => 'cap-nvy', 'name' => 'Navy cap', 'price' => 25.00]],
                shippingAddress: null,
                shippingCountry: 'US',
                billingCountry: 'US',
            ),
            default => throw new InvalidArgumentException("No sample order {$id}"),
        };
    }
}

class Warehouse
{
    /**
     * @param  list<array{sku: string, name: string, price: float}>  $items
     * @return list<string> the names of the items that cannot ship
     */
    public function soldOut(array $items): array
    {
        $soldOut = array_filter($items, fn (array $item) => $item['sku'] === 'sold-out');

        return array_values(array_column($soldOut, 'name'));
    }
}

class RequireShippingAddress extends PipeRule
{
    public static $name = 'Require a shipping address';

    public static $description = 'We cannot ship to nowhere';

    public function requireShippingAddress($order)
    {
        if (blank($order->shippingAddress)) {
            $this->fatal('The order has no shipping address');
        }

        return self::SUCCESS;
    }
}

class ApplyDiscountCodes extends PipeRule
{
    public static $name = 'Apply discount codes';

    public static $description = 'Take any valid code off the subtotal';

    public function applyDiscountCodes($order)
    {
        if ($order->discountCode === null) {
            return $this->skip('No discount code');
        }

        if ($order->discountCode !== 'SPRING20') {
            $this->error("{$order->discountCode} is not a valid code");
        }

        $order->discount = round($order->subtotal() * 0.20, 2);
        $this->info(sprintf('%s applied: -$%.2f', $order->discountCode, $order->discount));

        return self::SUCCESS;
    }
}

class CalculateTax extends PipeRule
{
    public static $name = 'Calculate tax';

    public static $description = 'Sales tax on what the customer pays';

    public function calculateTax($order)
    {
        $order->tax = round(($order->subtotal() - $order->discount) * 0.0825, 2);
        $this->info(sprintf('Tax: $%.2f', $order->tax));

        return self::SUCCESS;
    }
}

class MatchBillingToShippingCountry extends PipeRule
{
    public static $name = 'Match billing to shipping country';

    public static $description = 'A card from one country shipping to another is a signal';

    public function matchBillingToShippingCountry($order)
    {
        if ($order->billingCountry !== $order->shippingCountry) {
            $order->addRiskSignal('Country mismatch');
            $this->warning("Billing {$order->billingCountry}, shipping {$order->shippingCountry}");

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}

class ScoreDeviceFingerprint extends PipeRule
{
    public static $name = 'Score the device fingerprint';

    public static $description = 'A device we have seen commit fraud is a signal';

    public function scoreDeviceFingerprint($order)
    {
        // A shop would ask its fraud service about the device here.

        return self::SUCCESS;
    }
}

class AuthorizePayment extends PipeRule
{
    public static $name = 'Authorize the payment';

    public static $description = 'Ask the card network to hold the money';

    public function authorizePayment($order)
    {
        // A shop would call its payment gateway here.

        return self::SUCCESS;
    }
}

class CapturePayment extends PipeRule
{
    public static $name = 'Capture the payment';

    public static $description = 'Take the money we were authorized for';

    public function capturePayment($order)
    {
        $order->charged = true;

        return self::SUCCESS;
    }
}

class ReserveInventory extends PipeRule
{
    public static $name = 'Reserve inventory';

    public static $description = 'Hold the stock for this order';

    public function reserveInventory($order)
    {
        // A shop would reserve the stock in its inventory system here.

        return self::SUCCESS;
    }
}

class SendToWarehouse extends PipeRule
{
    public static $name = 'Send to the warehouse';

    public static $description = 'Queue the order for picking and packing';

    public function sendToWarehouse($order)
    {
        $order->shipped = true;

        return self::SUCCESS;
    }
}

class EmailReceipt extends PipeRule
{
    public static $name = 'Email the receipt';

    public static $description = 'Tell the customer it is on its way';

    public function emailReceipt($order)
    {
        // A shop would queue the confirmation email here.

        return self::SUCCESS;
    }
}
