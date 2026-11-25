<?php

/*
 * The classes the click intake at the top of the README and the introduction uses but does
 * not show.
 *
 * They are in the global namespace because the pages show them that way. The docs runner
 * (tests/Support/DocsRunner.php) loads this file before it runs the PHP blocks of the pages.
 */

use Keaton\PipeDream\Contracts\ReceiptSummary;
use Keaton\PipeDream\PipeRule;

/**
 * The subject of the opening example: one ad click, as the request described it.
 */
class Click implements ReceiptSummary
{
    public ?string $browser = null;

    public ?string $location = null;

    public ?string $campaignLabel = null;

    /** @var list<string> */
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
    ) {}

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

    /** The headline of the receipt, in the words of the business. */
    public function receiptSummary(): ?string
    {
        return match (true) {
            $this->isDropped() => "Dropped: {$this->dropReason}",
            $this->settled => 'Recorded',
            default => null,
        };
    }
}

class RequireClickId extends PipeRule
{
    public function requireClickId($click)
    {
        if (blank($click->id)) {
            $this->fatal('No click id on the request, nothing to trace to an ad');
        }

        return self::SUCCESS;
    }
}

class ParseUserAgent extends PipeRule
{
    public function parseUserAgent($click)
    {
        preg_match('/(Chrome|Firefox|Safari)\/(\d+)/', $click->userAgent, $browser);

        $system = match (true) {
            str_contains($click->userAgent, 'Windows') => 'Windows',
            str_contains($click->userAgent, 'Mac') => 'macOS',
            default => 'Linux',
        };

        $click->browser = trim(($browser[1] ?? 'Unknown browser').' '.($browser[2] ?? '')).' on '.$system;
        $this->info($click->browser);

        return self::SUCCESS;
    }
}

class ResolveGeoIp extends PipeRule
{
    public function resolveGeoIp($click)
    {
        // An intake would ask its GeoIP database here.
        $places = ['198.51.100.7' => 'US, Austin TX', '203.0.113.23' => 'US, Ashburn VA'];

        $click->location = $places[$click->ip] ?? null;
        $this->info($click->location ?? 'Location unknown');

        return self::SUCCESS;
    }
}

class IsKnownBot extends PipeRule
{
    public function isKnownBot($click)
    {
        if (preg_match('/bot|crawler|spider|curl|python-requests/i', $click->userAgent) === 1) {
            $click->addBotSignal('Scripted client');
            $this->warning('User agent matches a scripted client');

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}

class DatacenterIpCheck extends PipeRule
{
    public function datacenterIpCheck($click)
    {
        // 203.0.113.0/24 stands in for a hosting provider's range.
        if (str_starts_with($click->ip, '203.0.113.')) {
            $click->addBotSignal('Hosting provider');
            $this->warning('IP belongs to a hosting provider');

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}

class HeadlessBrowserSignals extends PipeRule
{
    public function headlessBrowserSignals($click)
    {
        if (str_contains($click->userAgent, 'Headless')) {
            $click->addBotSignal('Headless browser');
            $this->warning('The browser says it is headless');

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}

class RateLimitPerFingerprint extends PipeRule
{
    public function rateLimitPerFingerprint($click)
    {
        if ($click->clicksFromThisDevice > 5) {
            $click->addBotSignal('Click rate');
            $this->warning("{$click->clicksFromThisDevice} clicks from this device in a minute");

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}

class MatchToCampaign extends PipeRule
{
    public function matchToCampaign($click)
    {
        $campaigns = ['spring-sale' => 'Spring Sale · Search'];

        if (! isset($campaigns[$click->campaign])) {
            $this->fatal("Unknown campaign {$click->campaign}");
        }

        $click->campaignLabel = $campaigns[$click->campaign];
        $this->info($click->campaignLabel);

        return self::SUCCESS;
    }
}

class DeduplicateWithinWindow extends PipeRule
{
    public function deduplicateWithinWindow($click)
    {
        $this->info($click->clicksInWindow === 0 ? 'First click in the window' : 'Repeat click, counted once');

        return self::SUCCESS;
    }
}

class AssignLastTouch extends PipeRule
{
    public function assignLastTouch($click)
    {
        $this->info("Credit: {$click->campaignLabel}");

        return self::SUCCESS;
    }
}

class WriteClickRow extends PipeRule
{
    public function writeClickRow($click)
    {
        // An intake would insert the click row here.
        $this->info('Row written');

        return self::SUCCESS;
    }
}

class UpdateCampaignCounters extends PipeRule
{
    public function updateCampaignCounters($click)
    {
        // An intake would add one to the campaign's click counter here.
        $this->info('Clicks +1');

        return self::SUCCESS;
    }
}

class FirePostback extends PipeRule
{
    public function firePostback($click)
    {
        // An intake would call the advertiser's postback URL here.
        $this->info('Advertiser notified, HTTP 200');

        return self::SUCCESS;
    }
}
