<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\PipeRule;
use Keaton\PipeDream\Strategies\DatabasePersistStrategy;
use Keaton\PipeDream\Tests\Support\DocsRunner;
use Keaton\PipeDream\Tests\TestCase;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The README and the docs pages (docs/*.md), run as written.
 *
 * Every PHP block is executed and every output block is compared with what its script
 * prints, with timings and paths taken out. The test class shown on the testing page is
 * run method by method. The opening of the README and the introduction shows pieces of an
 * app, so each piece is run here with a click to work on. The rest of the tests keep the
 * pages in shape: grouped, linked, flat and short. The classes the pages use but do not
 * show are in tests/Fixtures/Docs.
 */
class DocsExamplesTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    /** The nav groups, in the order the sidebar shows them. */
    private const GROUPS = [
        'Start here',
        'Building blocks',
        'Deciding what happens',
        'In production',
        'Walkthroughs',
        'Reference',
    ];

    /** The click of the opening: a person on Chrome in Austin who clicked a Spring Sale ad. */
    private const ACCEPTED_CLICK = <<<'PHP'
        $click = new Click(
            id: 'clk_48213',
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0.0.0 Safari/537.36',
            ip: '198.51.100.7',
            campaign: 'spring-sale',
        );
        PHP;

    /** A script on a hosting provider's server: three bot checks flag it. */
    private const SCRIPTED_CLICK = <<<'PHP'
        $click = new Click(
            id: 'clk_48214',
            userAgent: 'python-requests/2.31 HeadlessChrome/124.0.0.0',
            ip: '203.0.113.23',
            campaign: 'spring-sale',
        );
        PHP;

    /** A request that carries no click id: the first rule stops the flow. */
    private const CLICK_WITHOUT_AN_ID = <<<'PHP'
        $click = new Click(
            id: null,
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0.0.0 Safari/537.36',
            ip: '198.51.100.7',
            campaign: 'spring-sale',
        );
        PHP;

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('view.paths', [__DIR__.'/../Fixtures/Docs/views']);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(self::ROOT.'/database/migrations');
    }

    /** @return array<string, array{string, ?string}> */
    public static function scripts(): array
    {
        return DocsRunner::scripts();
    }

    /** @dataProvider scripts */
    #[DataProvider('scripts')]
    public function test_every_script_prints_the_output_shown_under_it(string $script, ?string $shown): void
    {
        // Every example starts from the package's defaults: nothing is saved unless the
        // example itself says so.
        $this->assertFalse($this->app->bound(PipeLoggerPersistStrategy::class));

        $printed = DocsRunner::run($script);

        $this->assertSame(
            DocsRunner::normalize((string) $shown),
            DocsRunner::normalize($printed),
            $shown === null
                ? "This script has no output block, so it must print nothing. It printed:\n{$printed}"
                : "The script printed:\n{$printed}"
        );
    }

    /** @return array<string, array{class-string, string}> */
    public static function shownTests(): array
    {
        return DocsRunner::tests();
    }

    /** @dataProvider shownTests */
    #[DataProvider('shownTests')]
    public function test_every_test_the_testing_page_shows_passes(string $class, string $method): void
    {
        DocsRunner::boot();

        $before = Assert::getCount();

        // Called inside this test's application. Its assertions count as this test's.
        (new $class($method))->{$method}();

        $this->assertGreaterThan($before, Assert::getCount(), "{$method} asserted nothing");
    }

    public function test_the_pages_show_scripts_definitions_and_a_test_class(): void
    {
        $this->assertGreaterThanOrEqual(12, count(DocsRunner::scripts()));
        $this->assertGreaterThanOrEqual(8, count(DocsRunner::declaredClasses()));
        $this->assertGreaterThanOrEqual(4, count(DocsRunner::tests()));
    }

    public function test_every_code_block_is_php_its_output_or_a_shell_command(): void
    {
        foreach (DocsRunner::sources() as $name => $markdown) {
            $this->assertStringNotContainsString('PENDING', $markdown, "{$name} has an output that was never filled in");

            foreach (DocsRunner::blocks($name, $markdown) as $block) {
                // A `text` block that is not the output of the script before it is left
                // with the kind `text`, and nothing checks it.
                $this->assertContains($block['kind'], ['definition', 'script', 'bash'], "{$name} has a {$block['kind']} block that no script prints");
            }
        }
    }

    public function test_no_code_line_is_wider_than_the_page(): void
    {
        foreach (DocsRunner::sources() as $name => $markdown) {
            foreach (DocsRunner::blocks($name, $markdown) as $block) {
                if (! in_array($block['kind'], ['definition', 'script'], true)) {
                    continue;
                }

                foreach (explode("\n", $block['code']) as $line) {
                    $this->assertLessThanOrEqual(90, mb_strlen($line), "{$name} has a code line over 90 characters: {$line}");
                }
            }
        }
    }

    public function test_every_fixture_class_is_used(): void
    {
        DocsRunner::boot();

        $pages = implode("\n", DocsRunner::sources());
        $fixtures = implode("\n", array_map('file_get_contents', glob(self::ROOT.'/tests/Fixtures/Docs/*.php') ?: []));

        foreach (DocsRunner::fixtureClasses() as $class) {
            $onAPage = preg_match('/\b'.$class.'\b/', $pages) === 1;

            if (is_subclass_of($class, PipeRule::class)) {
                $this->assertTrue($onAPage, "The rule {$class} is in tests/Fixtures/Docs, but no page uses it");

                continue;
            }

            // A helper is used by a page, or by another fixture class.
            $this->assertTrue(
                $onAPage || preg_match_all('/\b'.$class.'\b/', $fixtures) > 1,
                "{$class} is in tests/Fixtures/Docs, but nothing uses it"
            );
        }
    }

    public function test_the_examples_folder_is_gone_and_nothing_points_at_it(): void
    {
        $this->assertDirectoryDoesNotExist(self::ROOT.'/docs/examples');

        $files = ['README.md', 'CONTRIBUTING.md', 'composer.json', 'phpunit.xml.dist', '.gitattributes'];

        foreach ($files as $file) {
            $this->assertStringNotContainsString('docs/examples', (string) file_get_contents(self::ROOT.'/'.$file), $file);
        }

        foreach (DocsRunner::pages() as $name => $markdown) {
            $this->assertStringNotContainsString('docs/examples', $markdown, $name);
            $this->assertStringNotContainsString('<!-- example:', $markdown, $name);
            $this->assertStringNotContainsString('<!-- output:', $markdown, $name);
        }
    }

    public function test_the_pages_are_grouped_in_sidebar_order(): void
    {
        $seen = [];

        foreach (DocsRunner::pages() as $name => $markdown) {
            $this->assertMatchesRegularExpression('/\A<!-- nav-group: .+ -->\n# \S/', $markdown, "{$name} does not start with its nav group and its title");

            preg_match('/\A<!-- nav-group: (.+) -->/', $markdown, $group);

            if (end($seen) !== $group[1]) {
                $this->assertNotContains($group[1], $seen, "{$name} reopens the group {$group[1]}");
                $seen[] = $group[1];
            }
        }

        $this->assertSame(self::GROUPS, $seen);
    }

    public function test_every_page_but_the_last_ends_with_a_link_to_the_next_page(): void
    {
        $names = array_keys(DocsRunner::pages());

        foreach (array_values(DocsRunner::pages()) as $i => $markdown) {
            $found = preg_match('/^\*\*Next:\*\* \[[^\]]+\]\(([\w-]+\.md)\)\.\n\z/m', $markdown, $next);

            if (! isset($names[$i + 1])) {
                $this->assertSame(0, $found, "{$names[$i]} is the last page, so it has no next page");

                continue;
            }

            $this->assertSame(1, $found, "{$names[$i]} does not end with a Next link");
            $this->assertSame($names[$i + 1], 'docs/'.$next[1], "{$names[$i]} skips a page");
        }
    }

    public function test_every_link_to_a_file_points_at_a_file_that_exists(): void
    {
        foreach (DocsRunner::sources() as $name => $markdown) {
            preg_match_all('/\]\((?!https?:)([^)#\s]+)(?:#[\w-]+)?\)/', $markdown, $links);

            foreach ($links[1] as $link) {
                $this->assertFileExists(dirname(self::ROOT.'/'.$name).'/'.$link, "{$name} links to {$link}");
            }
        }
    }

    public function test_the_docs_stay_a_flat_folder_of_short_pages(): void
    {
        $pages = DocsRunner::pages();

        $this->assertSame(
            array_map(fn (string $name) => realpath(self::ROOT.'/'.$name), array_keys($pages)),
            array_map('realpath', self::sorted(glob(self::ROOT.'/docs/*.md') ?: [])),
        );

        $this->assertSame(
            ['click-story.png'],
            array_values(array_map('basename', array_diff(glob(self::ROOT.'/docs/*') ?: [], glob(self::ROOT.'/docs/*.md') ?: []))),
            'docs/ holds only the pages and the one picture: no folders, no example files'
        );

        $this->assertGreaterThanOrEqual(10, count($pages));
        $this->assertLessThanOrEqual(13, count($pages));

        foreach ($pages as $name => $markdown) {
            $lines = substr_count($markdown, "\n");

            // The introduction also holds the opening, so it gets more room.
            $longest = $name === 'docs/index.md' ? 180 : 130;

            $this->assertGreaterThanOrEqual(40, $lines, "{$name} is shorter than 40 lines");
            $this->assertLessThanOrEqual($longest, $lines, "{$name} is longer than {$longest} lines");
        }
    }

    public function test_the_readme_and_the_introduction_open_with_the_same_click_and_checkout(): void
    {
        $readme = (string) file_get_contents(self::ROOT.'/README.md');

        // The receipt of a click, the flow it came from, two queries, and the checkout's receipt.
        $this->assertSame(DocsRunner::opening($readme), DocsRunner::opening(DocsRunner::pages()['docs/index.md']));

        $this->assertStringNotContainsString('<!--', $readme);
        $this->assertLessThanOrEqual(220, substr_count($readme, "\n"), 'The README is longer than 220 lines');

        // The README shows the picture from the root of the repository, the pages from docs/.
        $this->assertStringContainsString('<img src="docs/click-story.png"', $readme);
        $this->assertFileExists(self::ROOT.'/docs/click-story.png');

        // A docs page's address on the site is its file name without the NN- prefix.
        $this->assertStringContainsString('https://kevineaton.dev/docs/pipe-dream/your-first-flow/', $readme);
        $this->assertStringContainsString('https://kevineaton.dev/docs/pipe-dream/click-intake/', $readme);
    }

    public function test_the_flow_of_the_opening_prints_the_receipt_shown_above_it(): void
    {
        $opening = DocsRunner::opening((string) file_get_contents(self::ROOT.'/README.md'));

        $printed = DocsRunner::run($opening['flow'], self::ACCEPTED_CLICK, 'echo $envelope->receipt();');

        $this->assertSame(DocsRunner::normalize($opening['receipt']), DocsRunner::normalize($printed), "The flow printed:\n{$printed}");
    }

    public function test_a_dropped_click_gets_the_same_receipt_with_the_rules_that_said_no(): void
    {
        $opening = DocsRunner::opening((string) file_get_contents(self::ROOT.'/README.md'));

        $printed = DocsRunner::normalize(DocsRunner::run($opening['flow'], self::SCRIPTED_CLICK, 'echo $envelope->receipt();'));

        $this->assertStringContainsString('Click intake · Dropped: 3 automation signals', $printed);
        $this->assertStringContainsString('✗ IsKnownBot (ms) User agent matches a scripted client', $printed);
        $this->assertStringContainsString('✓ RateLimitPerFingerprint', $printed);
        $this->assertStringContainsString('Attribute: Which campaign gets the credit? · skipped: Dropped: 3 automation signals', $printed);
        $this->assertStringContainsString('↷ FirePostback skipped', $printed);
    }

    public function test_the_queries_of_the_opening_find_the_saved_runs(): void
    {
        $this->app->bind(PipeLoggerPersistStrategy::class, DatabasePersistStrategy::class);

        $opening = DocsRunner::opening((string) file_get_contents(self::ROOT.'/README.md'));

        // Two saved runs: the click of the opening, and one the first rule stopped.
        $accepted = DocsRunner::run($opening['flow'], self::ACCEPTED_CLICK, 'echo $envelope->runId();');
        $stopped = DocsRunner::run($opening['flow'], self::CLICK_WITHOUT_AN_ID, 'echo $envelope->runId();');

        $printed = DocsRunner::run(
            $opening['queries'],
            '$runId = '.var_export($accepted, true).';',
            'echo $receipt, PHP_EOL, $runs->pluck("run_id")->implode(",");'
        );

        $lines = explode("\n", trim($printed));
        $withProblems = array_pop($lines);

        $this->assertSame(DocsRunner::normalize($opening['receipt']), DocsRunner::normalize(implode("\n", $lines)));
        $this->assertSame($stopped, $withProblems);
    }

    public function test_the_checkout_of_the_opening_is_the_receipt_the_checkout_prints(): void
    {
        $opening = DocsRunner::opening((string) file_get_contents(self::ROOT.'/README.md'));

        $printed = DocsRunner::run("echo CheckoutFlow::build()->subject(Orders::find('ord_48213'))->sendIt()->receipt();");

        $this->assertSame(DocsRunner::normalize($opening['checkout']), DocsRunner::normalize($printed), "The checkout printed:\n{$printed}");
    }

    /**
     * @param  list<string>  $paths
     * @return list<string> index.md first, then the rest by name
     */
    private static function sorted(array $paths): array
    {
        usort($paths, fn (string $a, string $b) => [basename($a) !== 'index.md', basename($a)] <=> [basename($b) !== 'index.md', basename($b)]);

        return $paths;
    }
}
