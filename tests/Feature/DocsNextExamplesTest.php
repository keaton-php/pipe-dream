<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Tests\TestCase;

/**
 * The proposed docs in docs-next/, run as written: every PHP block is its
 * fixture verbatim, and every output block is what its script prints
 * (timings, durations and the checkout path are normalized).
 */
class DocsNextExamplesTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_every_php_block_is_its_fixture_verbatim(): void
    {
        preg_match_all('/([^\n]*)\n```php\n(.*?)\n```/s', self::docs(), $blocks, PREG_SET_ORDER);

        $this->assertGreaterThanOrEqual(20, count($blocks));

        foreach ($blocks as [, $lineBefore, $shown]) {
            $this->assertMatchesRegularExpression('/^<!-- example: \S+ -->$/', $lineBefore, 'A PHP block in docs-next has no example marker');
            preg_match('/<!-- example: (\S+) -->/', $lineBefore, $marker);
            $this->assertFileExists(self::ROOT.'/'.$marker[1]);
            $this->assertSame(rtrim(file_get_contents(self::ROOT.'/'.$marker[1]), "\n"), $shown, "{$marker[1]} differs from its docs-next block");
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

    public function test_every_page_has_a_nav_group_and_links_land_on_pages(): void
    {
        $this->assertCount(20, self::pages());

        foreach (self::pages() as $page) {
            $text = file_get_contents($page);
            $this->assertMatchesRegularExpression('/^<!-- nav-group: .+ -->\n# /', $text, basename($page));

            preg_match_all('/\]\(([\w-]+\.md)(#[\w-]+)?\)/', $text, $links);
            foreach ($links[1] as $link) {
                $this->assertFileExists(dirname($page).'/'.$link, basename($page).' links to a missing page');
            }
        }
    }

    private function normalize(string $output): string
    {
        $output = str_replace(realpath(self::ROOT).'/', '', $output);
        $output = preg_replace('/\(\d+(?:\.\d+)? ?ms\)/', '(ms)', $output);
        $output = preg_replace('/"duration_ms": [\d.]+/', '"duration_ms": 0', $output);
        $output = preg_replace('/ {2,}/', ' ', $output);

        return trim($output);
    }

    private static function pages(): array
    {
        return glob(self::ROOT.'/docs-next/[0-9][0-9]-*.md');
    }

    private static function docs(): string
    {
        return implode("\n", array_map('file_get_contents', self::pages()));
    }
}
