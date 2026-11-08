<?php

namespace Keaton\PipeDream\Tests\Feature;

use Keaton\PipeDream\Contracts\ReceiptFormat;
use Keaton\PipeDream\Formats\ArrayFormat;
use Keaton\PipeDream\Formats\ConsoleFormat;
use Keaton\PipeDream\Formats\MarkdownFormat;
use Keaton\PipeDream\PipeEnvelope;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\Receipt;
use Keaton\PipeDream\Tests\Fixtures\Rules\ErrorRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FatalRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\FirstRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\SecondRule;
use Keaton\PipeDream\Tests\Fixtures\Rules\ThirdRule;
use Keaton\PipeDream\Tests\TestCase;
use stdClass;

class ReceiptTest extends TestCase
{
    private function run_(): PipeEnvelope
    {
        return PipeManager::create([
            PipeHub::create()->name('Check')->description('Look it over')->addPipes([FirstRule::class, ErrorRule::class]),
            PipeHub::create()->name('Optional')->addPipe(ThirdRule::class)->skip(),
            PipeHub::create()->name('Finish')->addPipe(SecondRule::class)->skipIf(fn () => 'Nothing to finish'),
        ])->name('Click intake')->subject(new stdClass())->sendIt();
    }

    public function test_a_receipt_lists_the_hubs_in_order_with_their_steps(): void
    {
        $receipt = $this->run_()->receipt();

        $this->assertSame('Click intake', $receipt->flowName());
        $this->assertSame(['Check', 'Optional', 'Finish'], $receipt->hubs()->map->name()->all());
        $this->assertSame(['Look it over', null, null], $receipt->hubs()->map->description()->all());
        $this->assertSame([['FirstRule', 'ErrorRule'], [], ['SecondRule']], $receipt->hubs()->map(fn ($hub) => $hub->steps()->map->name()->all())->all());
        $this->assertSame([false, true, true], $receipt->hubs()->map->wasSkipped()->all());
        $this->assertSame([null, null, 'Nothing to finish'], $receipt->hubs()->map->skipReason()->all());
    }

    public function test_the_receipt_carries_the_envelopes_run_id(): void
    {
        $envelope = $this->run_();

        $this->assertSame($envelope->runId(), $envelope->receipt()->runId());
    }

    public function test_the_outcome_reads_how_the_run_ended(): void
    {
        $run = fn (string $rule) => PipeHub::create()->addPipe($rule)->sendIt(new stdClass())->receipt();

        $this->assertSame(Receipt::COMPLETED, $run(FirstRule::class)->outcome());
        $this->assertSame(Receipt::COMPLETED_WITH_ERRORS, $run(ErrorRule::class)->outcome());
        $this->assertSame(Receipt::STOPPED, $run(FatalRule::class)->outcome());
    }

    public function test_a_rule_that_returns_fail_without_an_error_line_counts_as_an_error(): void
    {
        $receipt = PipeHub::create()->addPipe(fn () => \Keaton\PipeDream\PipeRule::FAIL)->sendIt(new stdClass())->receipt();

        $this->assertSame(Receipt::COMPLETED_WITH_ERRORS, $receipt->outcome());
    }

    public function test_the_console_format(): void
    {
        $expected = <<<'TEXT'
            Click intake · completed with errors

            Check — Look it over
              ✓ FirstRule
              ✗ ErrorRule   Something is off

            Optional · skipped

            Finish · skipped: Nothing to finish
              ↷ SecondRule

            TEXT;

        $this->assertSame($expected, $this->run_()->receipt()->format(new ConsoleFormat(times: false)));
    }

    public function test_the_console_format_shows_times_and_can_leave_out_descriptions(): void
    {
        $text = $this->run_()->receipt()->format(new ConsoleFormat(descriptions: false));

        $this->assertMatchesRegularExpression('/^Click intake · completed with errors \(\d+\.\d{2} ms\)$/m', $text);
        $this->assertMatchesRegularExpression('/^  ✓ FirstRule  \(\d+\.\d{2} ms\)$/m', $text);
        $this->assertMatchesRegularExpression('/^  ↷ SecondRule skipped$/m', $text);
        $this->assertStringContainsString("\nCheck\n", $text);
        $this->assertStringNotContainsString('Look it over', $text);
    }

    public function test_the_console_format_can_show_origins_colors_and_the_run_id(): void
    {
        $envelope = PipeHub::create()->addPipe(ErrorRule::class)->sendIt(new stdClass());
        $text = $envelope->receipt()->format(new ConsoleFormat(colors: true, times: false, origins: true, runId: true));

        $this->assertStringContainsString("\033[31m✗\033[0m ErrorRule  Something is off", $text);
        $this->assertStringContainsString('Run '.$envelope->runId(), $text);
        $this->assertMatchesRegularExpression('/^ +at .+ErrorRule\.php:\d+$/m', $text);
    }

    public function test_steps_outside_a_named_hub_are_listed_without_a_heading(): void
    {
        $text = PipeHub::create()->addPipe(FirstRule::class)->sendIt(new stdClass())->receipt()->format(new ConsoleFormat(times: false));

        $this->assertSame("Run · completed\n\n  ✓ FirstRule\n", $text);
    }

    public function test_the_markdown_format(): void
    {
        $expected = <<<'MARKDOWN'
            **Click intake** · completed with errors

            **Check** — Look it over

            | | Step | Notes |
            | :-: | --- | --- |
            | ✓ | FirstRule |  |
            | ✗ | ErrorRule | Something is off |

            **Optional** · skipped

            **Finish** · skipped: Nothing to finish

            | | Step | Notes |
            | :-: | --- | --- |
            | ↷ | SecondRule |  |

            MARKDOWN;

        $this->assertSame($expected, $this->run_()->receipt()->format(new MarkdownFormat(times: false)));
    }

    public function test_the_markdown_format_escapes_what_would_break_a_table(): void
    {
        $markdown = PipeHub::create()->name('A | B')->addPipe(FirstRule::class)->sendIt(new stdClass())->receipt()->format(new MarkdownFormat(times: false));

        $this->assertStringContainsString('**A \| B**', $markdown);
    }

    public function test_the_array_format(): void
    {
        $array = $this->run_()->receipt()->format(new ArrayFormat());

        $this->assertSame('Click intake', $array['flow']);
        $this->assertSame(Receipt::COMPLETED_WITH_ERRORS, $array['outcome']);
        $this->assertIsFloat($array['duration_ms']);
        $this->assertSame(['Check', 'Optional', 'Finish'], array_column($array['hubs'], 'name'));
        $this->assertSame('Nothing to finish', $array['hubs'][2]['skip_reason']);

        $error = $array['hubs'][0]['steps'][1];
        $this->assertSame('ErrorRule', $error['name']);
        $this->assertSame(ErrorRule::class, $error['rule']);
        $this->assertSame('error', $error['status']);
        $this->assertSame(['Something is off'], $error['messages']);
        $this->assertStringContainsString('ErrorRule.php:', $error['origin']);
    }

    public function test_echoing_a_receipt_prints_the_console_format(): void
    {
        $receipt = $this->run_()->receipt();

        $this->assertSame($receipt->format(new ConsoleFormat()), (string) $receipt);
    }

    public function test_print_echoes_the_receipt_in_the_given_format(): void
    {
        $receipt = $this->run_()->receipt();

        ob_start();
        $receipt->print(new MarkdownFormat(times: false));

        $this->assertSame($receipt->format(new MarkdownFormat(times: false)), ob_get_clean());
    }

    public function test_the_format_bound_in_the_container_is_the_default(): void
    {
        $this->app->instance(ReceiptFormat::class, new MarkdownFormat(times: false));

        $receipt = $this->run_()->receipt();

        $this->assertSame($receipt->format(new MarkdownFormat(times: false)), (string) $receipt);
    }

    public function test_a_default_format_that_returns_an_array_echoes_as_json(): void
    {
        $this->app->instance(ReceiptFormat::class, new ArrayFormat());

        $this->assertSame('Click intake', json_decode((string) $this->run_()->receipt(), true)['flow']);
    }

    public function test_any_class_implementing_receipt_format_is_a_format(): void
    {
        $oneLine = new class implements ReceiptFormat
        {
            public function format(Receipt $receipt): mixed
            {
                return "{$receipt->flowName()}: {$receipt->steps()->count()} steps, {$receipt->outcome()}";
            }
        };

        $this->assertSame('Click intake: 3 steps, completed with errors', $this->run_()->receipt()->format($oneLine));
    }

    public function test_a_receipt_read_from_a_bare_logger_finds_the_flow_name(): void
    {
        $receipt = new Receipt($this->run_()->logger());

        $this->assertSame('Click intake', $receipt->flowName());
        $this->assertNull($receipt->runId());
    }

    public function test_a_subject_with_a_receipt_summary_heads_the_receipt(): void
    {
        $envelope = PipeHub::create()->name('Check')->addPipe(FirstRule::class)->sendIt(new SummarisedSubject('On hold: two fraud signals'));

        $this->assertSame('On hold: two fraud signals', $envelope->receipt()->summary());
        $this->assertStringStartsWith("Check · On hold: two fraud signals\n", $envelope->receipt()->format(new ConsoleFormat(times: false)));
        $this->assertStringStartsWith('**Check** · On hold: two fraud signals', $envelope->receipt()->format(new MarkdownFormat(times: false)));
        $this->assertSame('On hold: two fraud signals', $envelope->receipt()->format(new ArrayFormat())['summary']);
        $this->assertSame(Receipt::COMPLETED, $envelope->receipt()->outcome());
    }

    public function test_the_summary_is_recorded_as_the_run_ends(): void
    {
        $subject = new SummarisedSubject('Placed');
        $envelope = PipeManager::create([PipeHub::create()->addPipe(FirstRule::class)])->subject($subject)->sendIt();
        $subject->summary = 'Changed later';

        $this->assertSame('Placed', $envelope->receipt()->summary());
        $this->assertSame('Placed', (new Receipt($envelope->logger()))->summary());
    }

    public function test_a_null_summary_falls_back_to_the_outcome(): void
    {
        $envelope = PipeHub::create()->addPipe(FatalRule::class)->sendIt(new SummarisedSubject(null));

        $this->assertNull($envelope->receipt()->summary());
        $this->assertStringStartsWith('Run · stopped', $envelope->receipt()->format(new ConsoleFormat(times: false)));
    }

    public function test_a_subject_without_receipt_summary_has_none(): void
    {
        $this->assertNull($this->run_()->receipt()->summary());
        $this->assertSame([], $this->run_()->logger()->recordsByKey(Receipt::SUMMARY_KEY)->all());
    }

    public function test_a_failing_summary_is_logged_and_does_not_break_the_run(): void
    {
        $envelope = PipeHub::create()->addPipe(FirstRule::class)->sendIt(new SummarisedSubject(new \RuntimeException('no summary')));

        $this->assertSame('receiptSummary() failed: no summary', $envelope->logger()->warnings()->first()['data']);
        $this->assertSame(Receipt::COMPLETED, $envelope->receipt()->outcome());
    }
}

class SummarisedSubject implements \Keaton\PipeDream\Contracts\ReceiptSummary
{
    public function __construct(public mixed $summary)
    {
    }

    public function receiptSummary(): ?string
    {
        if ($this->summary instanceof \Throwable) {
            throw $this->summary;
        }

        return $this->summary;
    }
}
