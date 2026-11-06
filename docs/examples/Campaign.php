<?php

class Campaign
{
    public function __construct(
        public string $slug,
        public array $countries,
        public string $type = 'offer',
        public bool $bot_checks_disabled = false,
        public bool $paused = false,
    ) {
    }

    public static function find(?string $slug): ?self
    {
        $campaigns = [
            'spring-sale' => new self('spring-sale', ['US', 'CA']),
            'weekly-digest' => new self('weekly-digest', ['US', 'CA'], 'newsletter'),
            'quiet-launch' => new self('quiet-launch', ['US'], 'offer', true),
            'paused-promo' => new self('paused-promo', ['US'], 'offer', false, true),
        ];

        return $campaigns[$slug] ?? null;
    }

    public function isNewsletter(): bool
    {
        return $this->type === 'newsletter';
    }
}
