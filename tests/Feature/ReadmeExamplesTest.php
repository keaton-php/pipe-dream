<?php

namespace Keaton\PipeDream\Tests\Feature;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Models\PipeDreamLog;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\Fixtures\Readme\BudgetLedger;
use Keaton\PipeDream\Tests\Fixtures\Readme\CheckAdvertiserSuppressionList;
use Keaton\PipeDream\Tests\Fixtures\Readme\CheckCampaignStatus;
use Keaton\PipeDream\Tests\Fixtures\Readme\ChooseLandingPage;
use Keaton\PipeDream\Tests\Fixtures\Readme\HandleAdClick;
use Keaton\PipeDream\Tests\Fixtures\Readme\RecordBudgetSpend;
use Keaton\PipeDream\Tests\Fixtures\Readme\ResolveRegion;
use Keaton\PipeDream\Tests\TestCase;

/**
 * Every code example in README.md, run as written.
 *
 * Each example in the README is preceded by an `<!-- example: path -->`
 * marker naming its fixture under tests/Fixtures/Readme; the first tests
 * here prove the README shows exactly that fixture's code. A script's
 * `expression;   // value` lines are evaluated and compared with the value
 * the comment claims ("..." in a quoted value matches anything).
 */
class ReadmeExamplesTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../Fixtures/Readme';

    private const CLICK = ['id' => 'clk_123', 'campaign' => 'spring-sale', 'country' => 'CA'];

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['view']->addLocation(self::FIXTURES.'/views');
    }

    // ------------------------------------------------------------------
    // The README shows the fixtures, and nothing untested

    public function test_every_example_in_the_readme_is_its_fixture_verbatim(): void
    {
        preg_match_all('/<!-- example: (\S+) -->\n```php\n(.*?)\n```/s', $this->readme(), $examples, PREG_SET_ORDER);

        $this->assertGreaterThanOrEqual(20, count($examples));

        foreach ($examples as [, $fixture, $shown]) {
            $this->assertSame($this->exampleSource($fixture), $shown, "README example {$fixture} differs from its fixture");
        }
    }

    public function test_every_php_block_in_the_tutorial_sections_is_backed_by_a_fixture(): void
    {
        $readme = $this->readme();
        $tutorial = substr($readme, strpos($readme, "\n## One hub, with closures"), strpos($readme, "\n## See it running") - strpos($readme, "\n## One hub, with closures"));

        preg_match_all('/(.*)\n```php\n/', $tutorial, $blocks);

        $this->assertNotEmpty($blocks[1]);

        foreach ($blocks[1] as $lineBefore) {
            $this->assertMatchesRegularExpression('/^<!-- example: \S+ -->$/', $lineBefore, 'A PHP block in the tutorial has no example marker');
        }
    }

    // ------------------------------------------------------------------
    // 2. One hub, with closures

    public function test_one_hub_with_closures(): void
    {
        ['envelope' => $envelope, 'click' => $click] = $this->runScript('click-check.php');

        $this->assertSame('North America', $envelope->get('region'));
        $this->assertSame('https://example.test/spring-sale', $click->destination);
        $this->assertCount(2, $envelope->results()->successes());
    }

    public function test_the_timeline_shown_is_the_timeline_the_example_produces(): void
    {
        ['envelope' => $envelope] = $this->runScript('click-check.php');

        ob_start();
        $this->runScript('render-timeline.php', ['envelope' => $envelope]);
        $printed = ob_get_clean();

        $shown = $this->fencedBlockAfter('<!-- output: scripts/click-check.php -->');

        // Everything but the timings, which differ on every run.
        $this->assertSame($this->withoutTimes($shown), $this->withoutTimes($printed));
    }

    // ------------------------------------------------------------------
    // 3. The same hub, with PipeRule classes

    public function test_the_same_hub_with_rule_classes(): void
    {
        $vars = $this->runScript('rule-hub.php', ['click' => (object) self::CLICK]);

        $this->assertSame(3, $this->assertCommentedValues('rule-hub.php', $vars));

        $envelope = $vars['envelope'];
        $this->assertSame('Visitor is in North America', $envelope->logger()->infos()->firstWhere('key', ResolveRegion::class)['data']);
        $this->assertSame(PipeRule::SUCCESS, $envelope->get(ChooseLandingPage::class));
        $this->assertSame('Resolve the region', ResolveRegion::$name);
    }

    public function test_the_run_method_is_the_one_named_after_the_class(): void
    {
        $this->assertSame(['resolveRegion'], (new ResolveRegion())->getExtendedClassMethods());
    }

    // ------------------------------------------------------------------
    // 4. What a rule can return

    public function test_what_a_rule_can_return(): void
    {
        $run = fn (string $campaign) => PipeHub::create()
            ->addPipes([CheckCampaignStatus::class, ChooseLandingPage::class])
            ->sendIt((object) ['id' => 'clk', 'campaign' => $campaign, 'destination' => null]);

        $live = $run('spring-sale');
        $this->assertSame(PipeRule::SUCCESS, $live->get(CheckCampaignStatus::class));
        $this->assertSame('https://example.test/spring-sale', $live->destination);

        $paused = $run('winter-sale');
        $this->assertSame(PipeRule::FAIL, $paused->get(CheckCampaignStatus::class));
        $this->assertStringStartsWith('Campaign winter-sale is paused', $paused->logger()->errors()->first()['data']);
        $this->assertSame('https://example.test/winter-sale', $paused->destination, 'after an error the next pipe runs');

        $test = $run('demo');
        $this->assertSame(PipeRule::SKIP, $test->get(CheckCampaignStatus::class));
        $this->assertSame('Test campaign, nothing to check', $test->logger()->infos()->firstWhere('key', CheckCampaignStatus::class)['data']);
        $this->assertNotNull($test->destination);

        $unknown = $run('nope');
        $this->assertSame(PipeRule::FATAL_FAIL, $unknown->get(CheckCampaignStatus::class));
        $this->assertNull($unknown->destination, 'a fatal skips the rest of the hub');

        // The fatal record points at the line in the rule that called fatal().
        $fatal = $unknown->logger()->fatals()->first();
        $this->assertStringEndsWith('CheckCampaignStatus.php', $fatal['context']['file']);
        $this->assertStringContainsString('$this->fatal(', file($fatal['context']['file'])[$fatal['context']['line'] - 1]);
    }

    public function test_the_return_values_are_the_numbers_the_table_shows(): void
    {
        $this->assertSame([1, 2, 3, 4], [PipeRule::SUCCESS, PipeRule::FAIL, PipeRule::FATAL_FAIL, PipeRule::SKIP]);
    }

    // ------------------------------------------------------------------
    // 5. Stages in order: PipeManager

    public function test_stages_in_order(): void
    {
        $vars = $this->runScript('manager.php');

        $this->assertSame(3, $this->assertCommentedValues('manager.php', $vars));

        $envelope = $vars['envelope'];
        $logs = $envelope->logs();
        $this->assertSame('PipeManager:sendIt', $logs[0]['key']);
        $this->assertSame('PipeManager:sendIt', end($logs)['key']);
        $this->assertSame(['Validate', 'Route'], $envelope->logger()->infos()
            ->where('data', 'Sending subject through all pipes')->pluck('context.name')->values()->all());
        $this->assertSame('Is this a click we can buy?', $logs[1]['context']['description']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $envelope->runId());
    }

    // ------------------------------------------------------------------
    // 6. Adding behaviour

    public function test_skip_if(): void
    {
        $vars = $this->runScript('skip-if.php');

        $this->assertSame(2, $this->assertCommentedValues('skip-if.php', $vars));
        $this->assertSame(
            "Skipped: the hub's skipIf() returned true",
            $vars['envelope']->logger()->infos()->firstWhere('key', CheckAdvertiserSuppressionList::class)['data']
        );

        // Without the skip, the same click is stopped by the suppression list.
        $this->assertTrue(PipeHub::create()->addPipe(CheckAdvertiserSuppressionList::class)->sendIt($vars['click'])->hasFatalErrors());
    }

    public function test_then(): void
    {
        $vars = $this->runScript('then.php');

        $this->assertSame(2, $this->assertCommentedValues('then.php', $vars));
    }

    public function test_a_manager_has_a_then_too(): void
    {
        $ran = false;
        PipeManager::create([PipeHub::create()])->subject((object) [])->then(function () use (&$ran) {
            $ran = true;
        })->sendIt();

        $this->assertTrue($ran);
    }

    public function test_stop_on_fatal(): void
    {
        $vars = $this->runScript('stop-on-fatal.php');

        $this->assertSame(2, $this->assertCommentedValues('stop-on-fatal.php', $vars));
    }

    public function test_without_stop_on_fatal_the_route_hub_still_runs(): void
    {
        $click = (object) ['id' => 'clk_127', 'campaign' => 'no-such-campaign', 'destination' => null];
        $managerThen = false;

        $envelope = PipeManager::create([
            PipeHub::create()->name('Validate')->addPipes([CheckCampaignStatus::class]),
            PipeHub::create()->name('Route')->addPipes([ChooseLandingPage::class]),
        ])->subject($click)->then(function () use (&$managerThen) {
            $managerThen = true;
        })->sendIt();

        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertSame('https://example.test/no-such-campaign', $click->destination);
        $this->assertTrue($managerThen);
    }

    public function test_with_stop_on_fatal_the_manager_then_does_not_run(): void
    {
        $managerThen = false;

        $envelope = PipeManager::create([
            PipeHub::create()->addPipes([CheckCampaignStatus::class])->stopOnFatal(),
        ])->subject((object) ['campaign' => 'no-such-campaign'])->then(function () use (&$managerThen) {
            $managerThen = true;
        })->sendIt();

        $this->assertInstanceOf(PipeEnvelope::class, $envelope);
        $this->assertFalse($managerThen);
    }

    public function test_on_failure_and_failure_is_fatal(): void
    {
        $this->app->instance(BudgetLedger::class, $this->downLedger());

        $vars = $this->runScript('on-failure.php');

        $this->assertSame(3, $this->assertCommentedValues('on-failure.php', $vars));
        $this->assertSame(PipeRule::FAIL, $vars['envelope']->get(RecordBudgetSpend::class));
    }

    public function test_without_failure_is_fatal_a_failure_ends_only_its_hub(): void
    {
        $this->app->instance(BudgetLedger::class, $this->downLedger());
        $click = (object) ['id' => 'clk_128', 'campaign' => 'spring-sale', 'needsReview' => false, 'destination' => null];
        $seen = null;

        PipeManager::create([
            PipeHub::create()
                ->addPipes([RecordBudgetSpend::class])
                ->onFailure(function ($click, $exception) use (&$seen) {
                    $click->needsReview = true;
                    $seen = $exception->getMessage();
                }),
            PipeHub::create()->addPipes([ChooseLandingPage::class]),
        ])->subject($click)->sendIt();

        $this->assertTrue($click->needsReview);
        $this->assertSame('Budget service unavailable', $seen);
        $this->assertSame('https://example.test/spring-sale', $click->destination);
    }

    public function test_the_budget_rule_succeeds_when_the_service_is_up(): void
    {
        $this->app->instance(BudgetLedger::class, $ledger = new BudgetLedger());

        $envelope = PipeHub::create()->addPipe(RecordBudgetSpend::class)->sendIt((object) ['campaign' => 'spring-sale']);

        $this->assertSame(PipeRule::SUCCESS, $envelope->get(RecordBudgetSpend::class));
        $this->assertSame([['spring-sale', 0.40]], $ledger->charges);
    }

    public function test_stop_on_error(): void
    {
        $vars = $this->runScript('stop-on-error.php');

        $this->assertSame(2, $this->assertCommentedValues('stop-on-error.php', $vars));
    }

    public function test_load_view(): void
    {
        ['__result' => $view] = $this->runScript('load-view.php');

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('challenge', $view->name());
        $this->assertSame(['click' => 'clk_130'], $view->getData());
    }

    // ------------------------------------------------------------------
    // 7. The log

    public function test_reading_the_log(): void
    {
        $vars = $this->runScript('log-reading.php');

        $this->assertSame(5, $this->assertCommentedValues('log-reading.php', $vars));
    }

    public function test_log_levels(): void
    {
        $vars = $this->runScript('log-level.php');

        $this->assertSame(2, $this->assertCommentedValues('log-level.php', $vars));
        $this->assertSame(['fatal', 'warning'], array_column($vars['envelope']->logs(), 'type'));
    }

    public function test_keeping_the_log_in_the_database(): void
    {
        $vars = $this->runScript('database.php');

        $this->assertSame(1, $this->assertCommentedValues('database.php', $vars));
        $this->assertSame(1, PipeDreamLog::count());

        $row = PipeDreamLog::first();
        $this->assertSame($vars['envelope']->runId(), $row->run_id);
        $this->assertEquals($vars['envelope']->logs(), json_decode($row->log, true));
    }

    public function test_binding_the_strategy_for_every_flow(): void
    {
        $this->runScript('bind-strategy.php');

        $this->assertTrue($this->app->bound(PipeLoggerPersistStrategy::class));

        PipeHub::create()->name('Any flow')->addPipe(ChooseLandingPage::class)->sendIt((object) ['campaign' => 'spring-sale']);

        $this->assertSame('Any flow', PipeDreamLog::first()->flow);
    }

    public function test_reading_a_run_back(): void
    {
        $vars = $this->runScript('database.php');
        $vars = $this->runScript('rebuild.php', ['envelope' => $vars['envelope']]);

        $this->assertSame(3, $this->assertCommentedValues('rebuild.php', $vars));
    }

    // ------------------------------------------------------------------
    // 8. Putting it together

    private function click(array $query, string $ip = '203.0.113.9', string $userAgent = 'Mozilla/5.0'): Request
    {
        return Request::create('/click', 'GET', $query + ['click_id' => 'clk_200', 'pub' => 'pub-12', 'country' => 'US'], [], [], [
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $userAgent,
        ]);
    }

    private function handle(Request $request)
    {
        return $this->app->make(HandleAdClick::class)($request);
    }

    public function test_a_good_click_reaches_the_landing_page_and_is_logged_once(): void
    {
        $response = $this->handle($this->click(['campaign' => 'spring-sale']));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://example.test/landing/spring-sale', $response->getTargetUrl());

        $this->assertSame(1, PipeDreamLog::count());
        $row = PipeDreamLog::first();
        $this->assertSame('Ad click intake', $row->flow);
        $this->assertFalse($row->has_fatals);
        $this->assertStringStartsWith('Handled in', $row->logger()->recordsByKey('Intake')->first()['data']);
        $this->assertSame(8, $row->logger()->infos()->where('data', 'Sending subject through all pipes')->count());
    }

    public function test_an_unknown_campaign_ends_the_flow_at_validation(): void
    {
        $response = $this->handle($this->click(['campaign' => 'no-such-campaign']));

        $this->assertSame('https://example.test/thanks', $response->getTargetUrl());

        $row = PipeDreamLog::first();
        $this->assertTrue($row->has_fatals);
        $this->assertSame(1, $row->logger()->infos()->where('data', 'Sending subject through all pipes')->count());
    }

    public function test_a_country_the_campaign_does_not_buy_ends_the_flow_at_geo(): void
    {
        $response = $this->handle($this->click(['campaign' => 'spring-sale', 'country' => 'FR']));

        $this->assertSame('https://example.test/thanks', $response->getTargetUrl());
        $this->assertStringStartsWith('Visitor is in FR; campaign targets US, CA', PipeDreamLog::first()->logger()->fatals()->first()['data']);
    }

    public function test_suppression_is_skipped_for_a_newsletter_campaign(): void
    {
        $newsletter = $this->handle($this->click(['campaign' => 'weekly-digest', 'email' => 'opted-out@example.test']));
        $this->assertSame('https://example.test/landing/weekly-digest', $newsletter->getTargetUrl());

        $offer = $this->handle($this->click(['campaign' => 'spring-sale', 'email' => 'opted-out@example.test']));
        $this->assertSame('https://example.test/thanks', $offer->getTargetUrl());
    }

    public function test_a_graylisted_publisher_gets_the_challenge_view_and_the_click_is_still_logged(): void
    {
        $response = $this->handle($this->click(['campaign' => 'spring-sale', 'pub' => 'pub-77']));

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('challenge', $response->name());
        $this->assertSame(1, PipeDreamLog::count());
    }

    public function test_a_blacklisted_ip_is_flagged_and_sent_to_review(): void
    {
        $response = $this->handle($this->click(['campaign' => 'spring-sale'], '192.0.2.66'));

        $this->assertSame('https://example.test/review', $response->getTargetUrl());
    }

    public function test_two_bot_signals_send_the_click_to_review_unless_bot_checks_are_off(): void
    {
        $bot = $this->handle($this->click(['campaign' => 'spring-sale'], '198.51.100.20', 'ExampleBot/2.1'));
        $this->assertSame('https://example.test/review', $bot->getTargetUrl());

        $unchecked = $this->handle($this->click(['campaign' => 'quiet-launch'], '198.51.100.20', 'ExampleBot/2.1'));
        $this->assertSame('https://example.test/landing/quiet-launch', $unchecked->getTargetUrl());
    }

    // ------------------------------------------------------------------
    // helpers

    private function downLedger(): BudgetLedger
    {
        $ledger = new BudgetLedger();
        $ledger->available = false;

        return $ledger;
    }

    private function readme(): string
    {
        return file_get_contents(__DIR__.'/../../README.md');
    }

    private function fencedBlockAfter(string $marker): string
    {
        $readme = $this->readme();
        $start = strpos($readme, $marker."\n```");
        $this->assertNotFalse($start, "No {$marker} in the README");

        $body = substr($readme, strpos($readme, "\n", strpos($readme, '```', $start)) + 1);

        return substr($body, 0, strpos($body, "\n```"));
    }

    /** The part of a fixture the README shows. */
    private function exampleSource(string $fixture): string
    {
        $source = file_get_contents(self::FIXTURES.'/'.$fixture);

        if (str_contains($source, "// README:start\n")) {
            $source = explode('// README:end', explode("// README:start\n", $source, 2)[1], 2)[0];
        } elseif (preg_match('/^<\?php\n\nnamespace [^;]+;\n\n/', $source, $header)) {
            $source = substr($source, strlen($header[0]));
        }

        return rtrim($source, "\n");
    }

    /** Run a README script in this test's scope; returns its variables, plus `__result`. */
    private function runScript(string $script, array $vars = []): array
    {
        $file = self::FIXTURES.'/scripts/'.$script;

        return (function () use ($file, $vars) {
            extract($vars);
            $__result = require $file;
            unset($file, $vars);

            return get_defined_vars();
        })->call($this);
    }

    /**
     * Evaluate each top-level `expression;   // value` line in a script's
     * README part and compare it with the value the comment claims.
     * Returns how many lines were checked.
     */
    private function assertCommentedValues(string $script, array $vars): int
    {
        $source = file_get_contents(self::FIXTURES.'/scripts/'.$script);
        preg_match_all('/^use [^;]+;$/m', explode('// README:start', $source)[0], $uses);
        $imports = implode("\n", $uses[0])."\n";
        $checked = 0;

        foreach (explode("\n", $this->exampleSource('scripts/'.$script)) as $line) {
            if (! preg_match('/^(\S.*?);\s+\/\/ (.+)$/', $line, $parts) || preg_match('/(?<![=!<>])=(?![=>])/', $parts[1])) {
                continue;
            }

            if (($expected = $this->literal($parts[2])) === false) {
                continue;
            }

            $actual = (function ($__vars, $__code) {
                extract($__vars);

                return eval($__code);
            })->call($this, $vars, $imports."return {$parts[1]};");

            if (is_string($expected[0]) && str_contains($expected[0], '...')) {
                $pattern = '/^'.str_replace('\.\.\.', '.*', preg_quote($expected[0], '/')).'$/';
                $this->assertMatchesRegularExpression($pattern, (string) $actual, $line);
            } else {
                $this->assertSame($expected[0], $actual, $line);
            }

            $checked++;
        }

        return $checked;
    }

    /** [value] for a literal at the start of a comment, or false. */
    private function literal(string $comment)
    {
        if (preg_match("/^'((?:[^'\\\\]|\\\\.)*)'/", $comment, $m)) {
            return [stripslashes($m[1])];
        }

        if (preg_match('/^-?\d+\.\d+/', $comment, $m)) {
            return [(float) $m[0]];
        }

        if (preg_match('/^-?\d+/', $comment, $m)) {
            return [(int) $m[0]];
        }

        foreach (['true' => true, 'false' => false, 'null' => null] as $word => $value) {
            if (preg_match('/^'.$word.'\b/', $comment)) {
                return [$value];
            }
        }

        return false;
    }

    private function withoutTimes(string $timeline): string
    {
        return trim(preg_replace('/^\s*\d+\.\d{3} ms  /m', '', $timeline));
    }
}
