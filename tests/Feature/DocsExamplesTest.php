<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Tests\TestCase;

/**
 * The docs (docs/*.md), run as written: every PHP block is its fixture file
 * verbatim, every output block is what its script prints (timings, durations
 * and the repo path are normalized), and the pages are grouped, in order,
 * linking only to pages that exist. The test classes shown on the testing
 * page are run by the suite itself (see phpunit.xml).
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

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_every_php_block_is_its_fixture_verbatim(): void
    {
        preg_match_all('/([^\n]*)\n```php\n(.*?)\n```/s', self::docs(), $blocks, PREG_SET_ORDER);

        $this->assertGreaterThanOrEqual(20, count($blocks));

        foreach ($blocks as [, $lineBefore, $shown]) {
            $this->assertMatchesRegularExpression('/^<!-- example: \S+ -->$/', $lineBefore, 'A PHP block in the docs has no example marker');
            preg_match('/<!-- example: (\S+) -->/', $lineBefore, $marker);
            $this->assertFileExists(self::ROOT.'/'.$marker[1]);
            $this->assertSame(rtrim(file_get_contents(self::ROOT.'/'.$marker[1]), "\n"), $shown, "{$marker[1]} differs from its docs block");
        }
    }

    public static function outputs(): array
    {
        preg_match_all('/<!-- output: (\S+) -->\n```\w*\n(.*?)\n```/s', self::docs(), $blocks, PREG_SET_ORDER);

        $cases = [];
        foreach ($blocks as $i => [, $script, $shown]) {
            $cases["{$i}: {$script}"] = [$script, $shown];
        }

        return $cases;
    }

    /** @dataProvider outputs */
    #[\PHPUnit\Framework\Attributes\DataProvider('outputs')]
    public function test_every_output_shown_is_what_its_script_prints(string $script, string $shown): void
    {
        $this->assertFalse($this->app->bound(PipeLoggerPersistStrategy::class));

        ob_start();
        (function () use ($script) {
            require self::ROOT.'/'.$script;
        })();

        $this->assertSame($this->normalize($shown), $this->normalize(ob_get_clean()), "Output of {$script}");
    }

    public function test_pages_are_grouped_in_order_and_link_only_to_pages_that_exist(): void
    {
        $this->assertCount(20, self::pages());

        $seen = [];

        foreach (self::pages() as $page) {
            $text = file_get_contents($page);

            $this->assertMatchesRegularExpression('/^<!-- nav-group: .+ -->\n# /', $text, basename($page).' has no nav-group comment and heading');

            preg_match('/^<!-- nav-group: (.+) -->/', $text, $group);

            if (end($seen) !== $group[1]) {
                $this->assertNotContains($group[1], $seen, basename($page)." reopens the group {$group[1]}");
                $seen[] = $group[1];
            }

            preg_match_all('/\]\(([\w-]+\.md)(#[\w-]+)?\)/', $text, $links);

            foreach ($links[1] as $link) {
                $this->assertFileExists(dirname($page).'/'.$link, basename($page)." links to a missing page {$link}");
            }
        }

        $this->assertSame(self::GROUPS, $seen);
    }

    public function test_the_testing_pages_tests_are_shown_and_run_by_the_suite(): void
    {
        $tests = glob(self::ROOT.'/docs/examples/testing/*Test.php');

        $this->assertNotEmpty($tests);

        foreach ($tests as $file) {
            $this->assertStringContainsString('<!-- example: docs/examples/testing/'.basename($file).' -->', self::docs(), basename($file).' is not shown in the docs');
        }

        $this->assertStringContainsString('docs/examples/testing', file_get_contents(self::ROOT.'/phpunit.xml'));
    }

    private function normalize(string $output): string
    {
        $output = str_replace(realpath(self::ROOT).'/', '', $output);
        $output = preg_replace('/\(\d+(?:\.\d+)? ?ms\)/', '(ms)', $output);
        $output = preg_replace('/"duration_ms": [\d.]+/', '"duration_ms": 0', $output);
        $output = preg_replace('/ {2,}/', ' ', $output);

        return trim($output);
    }

    /** The docs pages in nav order: the home first, then docs/NN-*.md. */
    private static function pages(): array
    {
        $pages = glob(self::ROOT.'/docs/[0-9][0-9]-*.md');
        sort($pages);
        array_unshift($pages, self::ROOT.'/docs/index.md');

        return $pages;
    }

    private static function docs(): string
    {
        return implode("\n", array_map('file_get_contents', self::pages()));
    }
}
