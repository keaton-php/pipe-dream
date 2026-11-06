<?php

namespace Keaton\PipeDream\Tests\Fixtures\Readme;

use Illuminate\Http\Request;

/**
 * The subject of the full example: one ad click, as the request described
 * it. It has the same fields as the plain clicks in the earlier examples.
 */
class IncomingClick
{
    public array $params;

    public ?string $id;

    public ?string $campaign;

    public string $ip;

    public string $userAgent;

    public ?string $email;

    public ?string $publisher;

    public ?string $country = null;

    public array $flags = [];

    public bool $listed = false;

    public bool $complete = false;

    public ?string $destination = null;

    public function __construct(Request $request)
    {
        $this->params = $request->query();
        $this->id = $request->query('click_id');
        $this->campaign = $request->query('campaign');
        $this->ip = (string) $request->ip();
        $this->userAgent = (string) $request->userAgent();
        $this->email = $request->query('email');
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

    public function isListed(): bool
    {
        return $this->listed;
    }

    public function flag(string $reason): void
    {
        $this->flags[] = $reason;
    }

    public function isFlagged(): bool
    {
        return $this->flags !== [];
    }

    public function isTest(): bool
    {
        return $this->param('test') === '1';
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
