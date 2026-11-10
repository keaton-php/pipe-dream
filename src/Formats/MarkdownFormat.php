<?php

namespace Keaton\PipeDream\Formats;

use Keaton\PipeDream\Contracts\ReceiptFormat;
use Keaton\PipeDream\Receipt;
use Keaton\PipeDream\ReceiptHub;
use Keaton\PipeDream\StepRecord;

/**
 * The receipt as Markdown, a table per hub: ready for a support ticket, a
 * pull request or a chat message.
 */
class MarkdownFormat implements ReceiptFormat
{
    /**
     * @param  bool  $times  show each step's time and the run's total
     * @param  bool  $descriptions  show each hub's description beside its name
     * @param  bool  $origins  show the file and line each error or fatal was raised on
     * @param  bool  $runId  show the run id under the header
     */
    public function __construct(
        public bool $times = true,
        public bool $descriptions = true,
        public bool $origins = false,
        public bool $runId = false
    ) {
    }

    public function format(Receipt $receipt): string
    {
        $header = '**'.$this->escape($receipt->flowName() ?? 'Run').'** · '.$this->escape($receipt->summary() ?? $receipt->outcome());

        if ($this->times && $receipt->duration() !== null) {
            $header .= ' ('.$this->duration($receipt->duration()).')';
        }

        $blocks = [$header];

        if ($this->runId && $receipt->runId() !== null) {
            $blocks[] = 'Run `'.$receipt->runId().'`';
        }

        foreach ($receipt->hubs() as $hub) {
            if ($hub->name() !== null) {
                $blocks[] = $this->heading($hub);
            }

            if ($hub->steps()->isNotEmpty()) {
                $blocks[] = $this->table($hub);
            }
        }

        return implode("\n\n", $blocks)."\n";
    }

    protected function heading(ReceiptHub $hub): string
    {
        $heading = '**'.$this->escape($hub->name()).'**';

        if ($this->descriptions && $hub->description()) {
            $heading .= ' — '.$this->escape($hub->description());
        }

        if ($hub->skipReason() !== null) {
            $heading .= ' · skipped: '.$this->escape($hub->skipReason());
        } elseif ($hub->wasSkipped()) {
            $heading .= ' · skipped';
        }

        return $heading;
    }

    protected function table(ReceiptHub $hub): string
    {
        $rows = $this->times
            ? ['| | Step | Time | Notes |', '| :-: | --- | --: | --- |']
            : ['| | Step | Notes |', '| :-: | --- | --- |'];

        foreach ($hub->steps() as $step) {
            $cells = [ConsoleFormat::ICONS[$step->status()], $this->escape($step->name())];

            if ($this->times) {
                $cells[] = $this->time($step);
            }

            $cells[] = $this->notes($step, $hub);

            $rows[] = rtrim('| '.implode(' | ', $cells).' |');
        }

        return implode("\n", $rows);
    }

    protected function notes(StepRecord $step, ReceiptHub $hub): string
    {
        $notes = $hub->skipReason() !== null ? [] : $step->messages()->map(fn ($message) => $this->escape($message))->all();

        if ($this->origins && $step->origin() !== null) {
            $notes[] = '`'.str_replace('`', "'", $step->origin()).'`';
        }

        return implode('<br>', $notes);
    }

    protected function time(StepRecord $step): string
    {
        if ($step->wasSkipped()) {
            return 'skipped';
        }

        return $step->duration() === null ? '' : $this->duration($step->duration());
    }

    protected function escape(string $text): string
    {
        return str_replace(['\\', '|', '*', '_', '`', "\r\n", "\n"], ['\\\\', '\|', '\*', '\_', '\`', '<br>', '<br>'], $text);
    }

    /** Milliseconds as "0.42 ms", or "14.81 s" from a second up. */
    protected function duration(float $milliseconds): string
    {
        return $milliseconds >= 1000
            ? sprintf('%.2f s', $milliseconds / 1000)
            : sprintf('%.2f ms', $milliseconds);
    }
}
