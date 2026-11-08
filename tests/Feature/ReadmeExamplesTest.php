<?php

namespace Keaton\PipeDream\Tests\Feature;

use CheckTitleLength;
use CountWords;
use HandleAdClick;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Exceptions\PipeRuleLoadView;
use Keaton\PipeDream\Models\PipeDreamLog;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Tests\TestCase;
use RecordBudgetSpend;
use RejectEmptyTitle;
use SlugifyTitle;
use TidyTitle;

/**
 * Every code example in README.md, run as written.
 *
 * Each PHP block in the README is preceded by an `<!-- example: path -->`
 * marker naming a complete file under docs/examples, and shows that file
 * exactly. Scripts are run as they are; classes are autoloaded (composer
 * classmap). A script's `expression;   // value` lines are evaluated and
 * compared with the value the comment claims ("..." in a quoted value
 * matches anything).
 */
class ReadmeExamplesTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    private const FIXTURES = self::ROOT.'/docs/examples';

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
            $this->assertSame($this->exampleSource($fixture), $shown, "README example {$fixture} differs from its file");
        }

        // Nothing in an example file is hidden from the reader.
        foreach (array_merge(glob(self::FIXTURES.'/*.php'), glob(self::FIXTURES.'/scripts/*.php')) as $file) {
            $this->assertStringNotContainsString('README:start', file_get_contents($file), $file);
        }
    }

    public function test_every_php_block_in_the_readme_is_backed_by_a_fixture(): void
    {
        preg_match_all('/(.*)\n```php\n/', $this->readme(), $blocks);

        $this->assertGreaterThanOrEqual(20, count($blocks[1]));

        foreach ($blocks[1] as $lineBefore) {
            $this->assertMatchesRegularExpression('/^<!-- example: \S+ -->$/', $lineBefore, 'A PHP block in the README has no example marker');
        }
    }

    /**
     * The README's examples may only depend on what the README shows. A
     * test that runs one (runScript() or the full example's handle()) must
     * not configure the container itself: any configuration an example
     * needs is part of the example, so the reader sees it too.
     */
    public function test_example_tests_set_up_nothing_the_readme_does_not_show(): void
    {
        $class = new \ReflectionClass($this);
        $lines = file($class->getFileName());
        $checked = 0;

        foreach ($class->getMethods() as $method) {
            if ($method->class !== static::class || ! str_starts_with($method->name, 'test_') || $method->name === __FUNCTION__) {
                continue;
            }

            $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

            if (! str_contains($body, '$this->runScript(') && ! str_contains($body, '$this->handle(') && ! str_contains($body, '$this->assertExampleOutput(')) {
                continue;
            }

            $checked++;

            foreach (['->instance(', '->bind(', '->singleton(', '->extend(', '::fake(', 'config(', 'Config::'] as $setup) {
                $this->assertStringNotContainsString($setup, $body, "{$method->name} sets up {$setup} that the README does not show");
            }
        }

        $this->assertGreaterThanOrEqual(18, $checked);
    }

    public function test_readme_fixtures_carry_no_state_between_tests(): void
    {
        foreach ($this->fixtureClasses() as $fixture) {
            foreach ((new \ReflectionClass($fixture))->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                $this->assertTrue(
                    in_array($property->name, ['name', 'description', 'runFunction', 'saveResultAs'], true),
                    "{$fixture}::\${$property->name} is static state a README example could depend on"
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // Outputs: every output block shown is what its example prints

    public function test_every_output_shown_is_what_its_example_prints(): void
    {
        preg_match_all('/<!-- output: docs\/examples\/scripts\/(\S+) -->/', $this->readme(), $outputs);

        $this->assertGreaterThanOrEqual(9, count($outputs[1]));

        foreach ($outputs[1] as $script) {
            $this->assertExampleOutput($script);
        }
    }

    // ------------------------------------------------------------------
    // What a pipeline is / the same thing as a PipeHub

    public function test_the_laravel_pipeline_and_the_hub_tidy_the_title_the_same_way(): void
    {
        ob_start();
        ['post' => $viaPipeline] = $this->runScript('pipeline.php');
        ['post' => $viaHub] = $this->runScript('hub.php');
        ob_end_clean();

        $this->assertSame('Hello, Pipe Dream', $viaPipeline->title);
        $this->assertSame($viaPipeline->title, $viaHub->title);
    }

    // ------------------------------------------------------------------
    // Why that's better

    public function test_the_envelope_reads_through_to_the_subject(): void
    {
        $this->assertExampleOutput('envelope.php');
    }

    public function test_pipes_share_values_on_the_envelope(): void
    {
        $vars = $this->assertExampleOutput('state.php');

        $this->assertSame('in review', $vars['envelope']->get('state'));
    }

    public function test_every_step_is_logged_with_its_return_value(): void
    {
        $vars = $this->assertExampleOutput('hub-log.php');

        $this->assertSame(
            ['hello,   PIPE dream', 'hello, PIPE dream', 'Hello, Pipe Dream'],
            $vars['envelope']->results()->map->result()->values()->all()
        );
    }

    public function test_the_log_points_at_the_step_that_broke_the_title(): void
    {
        $vars = $this->assertExampleOutput('hub-log-broken.php');

        $this->assertSame(
            ['hello,   PIPE dream', 'hello,PIPEdream', 'Hello,pipedream'],
            $vars['envelope']->results()->map->result()->values()->all()
        );
    }

    // ------------------------------------------------------------------
    // Stack hubs into a PipeManager

    public function test_stacked_hubs_make_one_run_and_one_log(): void
    {
        $vars = $this->assertExampleOutput('stack.php');

        $logs = $vars['envelope']->logs();
        $this->assertSame('PipeManager:sendIt', $logs[0]['key']);
        $this->assertSame('PipeManager:sendIt', end($logs)['key']);
    }

    // ------------------------------------------------------------------
    // Rules

    public function test_rules_with_injection_shared_values_and_logging(): void
    {
        $vars = $this->assertExampleOutput('rules.php');

        $envelope = $vars['envelope'];
        $this->assertSame('Title is now "Hello, Pipe Dream"', $envelope->logger()->infos()->firstWhere('key', TidyTitle::class)['data']);
        $this->assertSame(3, $envelope->get('words'));
        $this->assertSame(['tidyTitle'], (new TidyTitle())->getExtendedClassMethods());
    }

    public function test_the_result_helpers_read_and_write_the_same_value_as_the_magic_property(): void
    {
        $envelope = PipeHub::create()
            ->addPipes([
                CountWords::class,
                fn ($post) => $post->set('words_seen', $post->get('words')),
            ])
            ->sendIt((object) ['title' => 'Hello Pipe Dream']);

        $this->assertSame(3, $envelope->get('words_seen'));
        $this->assertSame(3, $envelope->get('words'));
    }

    public function test_a_one_word_title_skips_the_length_check(): void
    {
        $envelope = PipeHub::create()
            ->addPipes([CountWords::class, CheckTitleLength::class])
            ->sendIt((object) ['title' => 'Hello']);

        $this->assertSame(PipeRule::SKIP, $envelope->get(CheckTitleLength::class));
        $this->assertSame('A one-word title is fine', $envelope->logger()->infos()->firstWhere('key', CheckTitleLength::class)['data']);
    }

    public function test_a_long_title_is_an_error_and_the_hub_carries_on(): void
    {
        $envelope = PipeHub::create()
            ->addPipes([CountWords::class, CheckTitleLength::class, SlugifyTitle::class])
            ->sendIt($post = (object) ['title' => 'one two three four five six seven eight nine ten eleven twelve thirteen']);

        $this->assertSame(PipeRule::FAIL, $envelope->get(CheckTitleLength::class));
        $this->assertStringStartsWith('The title has 13 words', $envelope->logger()->errors()->first()['data']);
        $this->assertNotNull($post->slug ?? null);
    }

    // ------------------------------------------------------------------
    // All together

    public function test_all_together(): void
    {
        $vars = $this->assertExampleOutput('together.php');

        $this->assertSame('Publish a post', $vars['envelope']->flowName());
    }

    public function test_a_draft_skips_the_publish_hub_and_stays_a_draft(): void
    {
        $source = file_get_contents(self::FIXTURES.'/scripts/together.php');
        $this->assertStringContainsString("'status' => 'ready'", $source);

        // The README's variation: the same file with 'status' => 'draft'.
        $draft = sys_get_temp_dir().'/pipe-dream-together-draft.php';
        file_put_contents($draft, str_replace("'status' => 'ready'", "'status' => 'draft'", $source));

        ob_start();
        $vars = (function () use ($draft) {
            require $draft;

            return get_defined_vars();
        })->call($this);
        ob_end_clean();
        unlink($draft);

        $this->assertSame('draft', $vars['post']->status);
        $this->assertSame(PipeRule::SKIP, $vars['envelope']->get(SlugifyTitle::class));
    }

    // ------------------------------------------------------------------
    // Controlling the flow

    public function test_the_controller_handles_success_fatal_and_redirect(): void
    {
        $this->assertExampleOutput('controller.php');
    }

    public function test_how_far_a_stop_reaches(): void
    {
        $vars = $this->assertExampleOutput('reach.php');

        // Rules after the stop have no result at all, rather than a skip.
        $this->assertStringNotContainsString('TidyTitle', ($vars['publish'])(''));
    }

    public function test_with_throw_fatals_a_fatal_that_stops_the_flow_lands_in_the_catch(): void
    {
        $vars = $this->assertExampleOutput('throw-fatals.php');

        // The last run's fatal was contained, so the flow carried on.
        $this->assertTrue($vars['envelope']->hasFatalErrors());
        $this->assertSame('', $vars['post']->slug);
    }

    public function test_by_default_a_fatal_never_leaves_send_it_as_an_exception(): void
    {
        $envelope = PipeManager::create([
            PipeHub::create()->addPipe(RejectEmptyTitle::class),
        ])->subject((object) ['title' => ''])->sendIt();

        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertSame(PipeRule::FATAL_FAIL, $envelope->get(RejectEmptyTitle::class));
    }

    public function test_a_view_hand_off_leaves_send_it(): void
    {
        $this->expectException(PipeRuleLoadView::class);

        PipeHub::create()
            ->addPipe(fn () => PipeRuleLoadView::load('challenge', ['click' => 'clk_1']))
            ->sendIt((object) []);
    }

    // ------------------------------------------------------------------
    // Keeping the log

    public function test_keeping_the_log_in_the_database(): void
    {
        $vars = $this->runScript('database.php');

        $this->assertSame(1, $this->assertCommentedValues('database.php', $vars));

        $row = PipeDreamLog::first();
        $this->assertSame($vars['envelope']->runId(), $row->run_id);
        $this->assertEquals($vars['envelope']->logs(), json_decode($row->log, true));

        $vars['envelope']->persist();
        $this->assertSame(1, PipeDreamLog::count(), 'saving the same run again updates its row');
    }

    public function test_reading_a_run_back(): void
    {
        $this->runScript('database.php');
        $vars = $this->runScript('rebuild.php');

        $this->assertSame(2, $this->assertCommentedValues('rebuild.php', $vars));
    }

    // ------------------------------------------------------------------
    // Reference: events

    public function test_events(): void
    {
        $vars = $this->runScript('events.php');

        $this->assertSame(1, $this->assertCommentedValues('events.php', $vars));
    }

    public function test_a_skipped_hub_dispatches_only_the_skip_event(): void
    {
        $fired = [];
        \Illuminate\Support\Facades\Event::listen([\BotChecksStarted::class, \BotChecksSkipped::class, \RuleStarting::class], function ($event) use (&$fired) {
            $fired[] = class_basename($event);
        });

        PipeHub::create()
            ->addPipe(\DetectHostingProviderIp::class)
            ->broadcastEventBefore(\BotChecksStarted::class)
            ->broadcastEventOnSkip(\BotChecksSkipped::class)
            ->broadcastEventBeforeEachPipe(\RuleStarting::class)
            ->skipIf(fn () => true)
            ->sendIt((object) ['ip' => '203.0.113.9']);

        $this->assertSame(['BotChecksSkipped'], $fired);
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
        foreach (array_merge($this->fixtureClasses(), [PipeLoggerPersistStrategy::class]) as $class) {
            $this->assertFalse($this->app->bound($class), "{$class} is bound before the full example runs");
        }

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

        // The click is charged to its own campaign, through the same rule as section 6.
        $this->assertSame('Charged spring-sale 0.40', $row->logger()->infos()->firstWhere('key', RecordBudgetSpend::class)['data']);
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
        $this->assertSame(['click' => 'clk_200'], $response->getData());
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

    /** @return array<class-string> every class declared under docs/examples */
    private function fixtureClasses(): array
    {
        $classes = [];

        foreach (glob(self::FIXTURES.'/*.php') as $file) {
            preg_match_all('/^class (\w+)/m', file_get_contents($file), $found);
            array_push($classes, ...$found[1]);
        }

        return $classes;
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

    /** An example file, as the README shows it: all of it. */
    private function exampleSource(string $path): string
    {
        return rtrim(file_get_contents(self::ROOT.'/'.$path), "\n");
    }

    /** Run a README script in this test's scope; returns its variables, plus `__result`. */
    private function runScript(string $script, array $vars = []): array
    {
        $file = self::FIXTURES.'/scripts/'.$script;

        // Every example starts from default fixtures: nothing bound that the
        // README does not show (an example that needs a binding makes it).
        foreach (array_merge($this->fixtureClasses(), [PipeLoggerPersistStrategy::class]) as $class) {
            $this->assertFalse($this->app->bound($class), "{$class} is bound before README example {$script} runs");
        }

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
        $source = $this->exampleSource('docs/examples/scripts/'.$script);
        preg_match_all('/^use [^;]+;$/m', $source, $uses);
        $imports = implode("\n", $uses[0])."\n";
        $checked = 0;

        foreach (explode("\n", $source) as $line) {
            if (str_starts_with($line, 'use ') || ! preg_match('/^(\S.*?);\s+\/\/ (.+)$/', $line, $parts) || preg_match('/(?<![=!<>])=(?![=>])/', $parts[1])) {
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

    /** Run an example and assert it prints exactly the output the README shows after it. Returns its variables. */
    private function assertExampleOutput(string $script): array
    {
        ob_start();
        $vars = $this->runScript($script);
        $printed = ob_get_clean();

        $shown = $this->fencedBlockAfter("<!-- output: docs/examples/scripts/{$script} -->");

        $this->assertSame($this->withoutTimes($shown), $this->withoutTimes($printed), "Output of {$script}");

        return $vars;
    }

    private function withoutTimes(string $timeline): string
    {
        return trim(preg_replace('/^\s*\d+\.\d{3} ms  /m', '', $timeline));
    }
}
