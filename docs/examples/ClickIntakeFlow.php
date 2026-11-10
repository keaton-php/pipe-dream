<?php

use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

class ClickIntakeFlow
{
    public static function build(): PipeManager
    {
        return PipeManager::create([

            PipeHub::create()
                ->name('Validate & start session')
                ->description('Is this a complete, signed click for a live campaign?')
                ->addPipes([
                    RequireTrackingParameters::class,
                    VerifyClickSignature::class,
                    RecognizeCampaign::class,
                    StartClickSession::class,
                ]),                          // a fatal here (a bad click) ends the whole flow

            PipeHub::create()
                ->name('Tracking')
                ->description('Attribution cookie and conversion-path tracking')
                ->addPipes([
                    SetAttributionCookie::class,
                    RecordConversionPath::class,
                ]),

            PipeHub::create()
                ->name('Geo exclusions')
                ->description('Is the visitor somewhere this campaign buys traffic?')
                ->addPipes([
                    ResolveVisitorCountry::class,
                    EnforceCampaignCountries::class,
                    EnforceAdvertiserGeoExclusions::class,
                ]),

            PipeHub::create()
                ->name('Suppression')
                ->description('Has this visitor asked the advertiser not to contact them?')
                ->addPipes([
                    CheckAdvertiserSuppressionList::class,
                ])
                ->skipIf(fn ($click) => $click->campaignSettings()->isNewsletter()),

            PipeHub::create()
                ->name('Blacklist & graylist')
                ->description('Checks that are never skipped')
                ->addPipes([
                    MatchIpBlacklist::class,
                    MatchPublisherBlacklist::class,
                    ChallengeGraylistedPublishers::class,
                ])
                ->then(fn ($click) => $click->isListed() ? $click->flag('Blacklisted') : null),

            PipeHub::create()
                ->name('Bot checks')
                ->description('Does this look like a person, not a script or a server?')
                ->addPipes([
                    DetectHostingProviderIp::class,
                    MatchKnownBotUserAgents::class,
                    ScoreClickVelocity::class,
                ])
                ->skipIf(fn ($click) => $click->campaignSettings()->bot_checks_disabled)
                ->then(fn ($click) => $click->get('bot_signals') >= 2 ? $click->flag('Two or more bot signals') : null),

            PipeHub::create()
                ->name('Fulfillment & caps')
                ->description('Daily caps and budget, only for clicks we will pay for')
                ->addPipes([
                    EnforceDailyClickCap::class,
                    RecordBudgetSpend::class,
                ])
                ->skipIf(fn ($click) => $click->isFlagged() || $click->isTest()
                    ? 'Flagged — not charged'
                    : null)
                ->errorsAreFatal()           // a failed charge ends this hub...
                ->fatalsStopThisHubOnly(),   // ...but the click is still routed

            PipeHub::create()
                ->name('Route')
                ->description('Landing page, review queue or a safe dead end')
                ->addPipes([
                    ChooseDestination::class,
                ]),

        ])
            ->name('Ad click intake')
            ->then(fn ($click) => $click->markComplete());
    }
}
