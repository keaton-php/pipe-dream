<?php

namespace Keaton\PipeDream\Formats;

use Keaton\PipeDream\Contracts\ReceiptFormat;
use Keaton\PipeDream\Receipt;
use Keaton\PipeDream\ReceiptHub;
use Keaton\PipeDream\StepRecord;

/**
 * The receipt as plain text: one line per step, grouped under its hub.
 *
 *     Checkout · Placed (0.41 ms)
 *
 *     Validate the order — Is this a complete order?
 *       ✓ RequireShippingAddress  (0.02 ms)
 *       ✓ ConfirmItemsInStock     (0.03 ms)  3 items in stock
 */
class ConsoleFormat implements ReceiptFormat
{
    const ICONS = ['ok' => '✓', 'skipped' => '↷', 'error' => '✗', 'fatal' => '■'];

    const STATUS_COLORS = ['ok' => '32', 'skipped' => '90', 'error' => '31', 'fatal' => '1;31'];

    const OUTCOME_COLORS = [
        Receipt::COMPLETED => '32',
        Receipt::COMPLETED_WITH_ERRORS => '33',
        Receipt::HANDED_OFF => '36',
        Receipt::STOPPED => '1;31',
    ];

    /**
     * @param  bool  $colors  color the symbols and outcome with ANSI codes
     * @param  bool  $times  show each step's time and the run's total
     * @param  bool  $descriptions  show each hub's description beside its name
     * @param  bool  $origins  show the file and line each error or fatal was raised on
     * @param  bool  $runId  show the run id under the header
     */
    public function __construct(
        public bool $colors = false,
        public bool $times = true,
        public bool $descriptions = true,
        public bool $origins = false,
        public bool $runId = false
    ) {
    }

    public function format(Receipt $receipt): string
    {
        $lines = [$this->header($receipt)];

        if ($this->runId && $receipt->runId() !== null) {
            $lines[] = 'Run '.$receipt->runId();
        }

        $nameWidth = (int) $receipt->steps()->map(fn (StepRecord $step) => mb_strlen($step->name()))->max();
        $timeWidth = (int) $receipt->steps()->map(fn (StepRecord $step) => mb_strlen($this->time($step)))->max();

        foreach ($receipt->hubs() as $hub) {
            $lines[] = '';

            if ($hub->name() !== null) {
                $lines[] = $this->heading($hub);
            }

            foreach ($hub->steps() as $step) {
                array_push($lines, ...$this->stepLines($step, $hub, $nameWidth, $timeWidth));
            }
        }

        return implode("\n", $lines)."\n";
    }

    protected function header(Receipt $receipt): string
    {
        $header = ($receipt->flowName() ?? 'Run').' · '.$this->paint($receipt->summary() ?? $receipt->outcome(), self::OUTCOME_COLORS[$receipt->outcome()]);

        if ($this->times && $receipt->duration() !== null) {
            $header .= sprintf(' (%.2f ms)', $receipt->duration());
        }

        return $this->paint($header, '1');
    }

    protected function heading(ReceiptHub $hub): string
    {
        $heading = $hub->name();

        if ($this->descriptions && $hub->description()) {
            $heading .= ' — '.$hub->description();
        }

        if ($hub->skipReason() !== null) {
            $heading .= ' · skipped: '.$hub->skipReason();
        } elseif ($hub->wasSkipped()) {
            $heading .= ' · skipped';
        }

        return $heading;
    }

    /**
     * @return array<int, string>
     */
    protected function stepLines(StepRecord $step, ReceiptHub $hub, int $nameWidth, int $timeWidth): array
    {
        // A hub that skipped every rule for one reason says it once, in its heading.
        $notes = $hub->skipReason() !== null ? [] : $step->messages()->all();

        if ($this->origins && $step->origin() !== null) {
            $notes[] = 'at '.$step->origin();
        }

        $line = '  '.$this->paint(self::ICONS[$step->status()], self::STATUS_COLORS[$step->status()]).' '.$this->pad($step->name(), $nameWidth);

        if ($this->times) {
            $line .= ' '.$this->pad($this->time($step), $timeWidth);
        }

        $lines = [rtrim($line.'  '.array_shift($notes))];
        $indent = str_repeat(' ', 4 + $nameWidth + ($this->times ? 1 + $timeWidth : 0) + 2);

        foreach ($notes as $note) {
            $lines[] = $indent.$note;
        }

        return $lines;
    }

    protected function time(StepRecord $step): string
    {
        if (! $this->times) {
            return '';
        }

        if ($step->wasSkipped()) {
            return 'skipped';
        }

        return $step->duration() === null ? '' : sprintf('(%.2f ms)', $step->duration());
    }

    protected function pad(string $text, int $width): string
    {
        return $text.str_repeat(' ', max(0, $width - mb_strlen($text)));
    }

    protected function paint(string $text, string $color): string
    {
        return $this->colors ? "\033[{$color}m{$text}\033[0m" : $text;
    }
}
