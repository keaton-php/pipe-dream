<?php

/*
 * The classes the click-intake walkthrough (docs/11-click-intake.md) uses but does not show.
 *
 * They are in the global namespace because the page shows them that way. The docs runner
 * (tests/Support/DocsRunner.php) loads this file before it runs the PHP blocks of the pages.
 */

use Illuminate\Http\Request;
use Keaton\PipeDream\Exceptions\PipeRuleLoadView;
use Keaton\PipeDream\PipeRule;

/**
 * The subject of the walkthrough: one ad click, as the request described it.
 */
class IncomingClick
{
    /** @var array<string, mixed> */
    public array $params;

    public ?string $id;

    public ?string $campaign;

    public string $ip;

    public string $userAgent;

    public ?string $publisher;

    /** @var list<string> */
    public array $flags = [];

    public bool $complete = false;

    public ?string $destination = null;

    public function __construct(Request $request)
    {
        $this->params = $request->query();
        $this->id = $request->query('click_id');
        $this->campaign = $request->query('campaign');
        $this->ip = (string) $request->ip();
        $this->userAgent = (string) $request->userAgent();
        $this->publisher = $request->query('pub');
    }

    public function param(string $key): ?string
    {
        return $this->params[$key] ?? null;
    }

    public function campaignSettings(): ?Campaign
    {
        return Campaign::find($this->campaign);
    }

    public function flag(string $reason): void
    {
        $this->flags[] = $reason;
    }

    public function isFlagged(): bool
    {
        return $this->flags !== [];
    }

    /** Why the advertiser is not billed for this click, or null when they are. */
    public function reasonNotToBill(): ?string
    {
        return $this->isFlagged() ? 'Flagged, not billed' : null;
    }

    public function markComplete(): void
    {
        $this->complete = true;
    }

    public function destination(): string
    {
        return $this->destination ?? 'https://example.test/thanks';
    }
}

class Campaign
{
    public function __construct(
        public string $slug,
        public bool $skipBotChecks = false,
        public bool $paused = false,
    ) {}

    public static function find(?string $slug): ?self
    {
        $campaigns = [
            'spring-sale' => new self('spring-sale'),
            'quiet-launch' => new self('quiet-launch', skipBotChecks: true),
            'paused-promo' => new self('paused-promo', paused: true),
        ];

        return $campaigns[$slug] ?? null;
    }
}

class BudgetLedger
{
    public function charge(string $campaign, float $amount): void
    {
        // A real ledger would write to the budget store here.
    }
}

class RequireTrackingParameters extends PipeRule
{
    public function requireTrackingParameters($click)
    {
        foreach (['click_id', 'campaign', 'pub'] as $key) {
            if ($click->param($key) === null) {
                $this->fatal("Missing required parameter: {$key}");
            }
        }

        return self::SUCCESS;
    }
}

class RecognizeCampaign extends PipeRule
{
    public function recognizeCampaign($click)
    {
        $campaign = $click->campaignSettings();

        if ($campaign === null) {
            $this->fatal("Unknown campaign {$click->campaign}");
        }

        if ($campaign->paused) {
            $this->fatal("Campaign {$campaign->slug} is paused");
        }

        return self::SUCCESS;
    }
}

class ChallengeGraylistedPublishers extends PipeRule
{
    public static $name = 'Challenge graylisted publishers';

    public static $description = 'Traffic from a doubtful publisher gets a challenge page';

    public function challengeGraylistedPublishers($click)
    {
        if (in_array($click->publisher, ['pub-77'], true)) {
            PipeRuleLoadView::load('challenge', ['click' => $click->id]);
        }

        return self::SUCCESS;
    }
}

class DetectHostingProviderIp extends PipeRule
{
    public static $name = 'Detect hosting-provider IPs';

    public static $description = 'People browse from homes and phones, not from server racks';

    public function detectHostingProviderIp($click)
    {
        // 198.51.100.0/24 stands in for a cloud provider's range.
        if (str_starts_with((string) $click->ip, '198.51.100.')) {
            $click->set('bot_signals', ($click->get('bot_signals') ?? 0) + 1);
            $this->warning("{$click->ip} belongs to a hosting provider");

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}

class MatchKnownBotUserAgents extends PipeRule
{
    public static $name = 'Match known bot user agents';

    public static $description = 'A user agent that says it is a bot is a bot';

    public function matchKnownBotUserAgents($click)
    {
        if (str_contains(strtolower((string) $click->userAgent), 'bot')) {
            $click->set('bot_signals', ($click->get('bot_signals') ?? 0) + 1);
            $this->warning('User agent looks like a bot');

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}

class ScoreClickVelocity extends PipeRule
{
    public function scoreClickVelocity($click)
    {
        // An intake would count the recent clicks from this visitor here.

        return self::SUCCESS;
    }
}

class EnforceDailyClickCap extends PipeRule
{
    public function enforceDailyClickCap($click)
    {
        // An intake would compare the campaign's clicks today with its daily cap here.

        return self::SUCCESS;
    }
}

class RecordBudgetSpend extends PipeRule
{
    public static $name = 'Record the budget spend';

    public static $description = 'Charge the click to the campaign budget';

    public function recordBudgetSpend($click, BudgetLedger $ledger)
    {
        $ledger->charge($click->campaign, 0.40);
        $this->info("Charged {$click->campaign} 0.40");

        return self::SUCCESS;
    }
}

class ChooseDestination extends PipeRule
{
    public function chooseDestination($click)
    {
        $click->destination = $click->isFlagged()
            ? 'https://example.test/review'
            : 'https://example.test/landing/'.$click->campaign;

        return self::SUCCESS;
    }
}
