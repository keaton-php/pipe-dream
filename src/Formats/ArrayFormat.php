<?php

namespace Keaton\PipeDream\Formats;

use Keaton\PipeDream\Contracts\ReceiptFormat;
use Keaton\PipeDream\Receipt;
use Keaton\PipeDream\ReceiptHub;
use Keaton\PipeDream\StepRecord;

/**
 * The receipt as a plain array, for an API response or a dashboard to
 * render its own way.
 */
class ArrayFormat implements ReceiptFormat
{
    /**
     * @return array{flow: ?string, run_id: ?string, outcome: string, summary: ?string, duration_ms: ?float, hubs: array<int, array>}
     */
    public function format(Receipt $receipt): array
    {
        return [
            'flow' => $receipt->flowName(),
            'run_id' => $receipt->runId(),
            'outcome' => $receipt->outcome(),
            'summary' => $receipt->summary(),
            'duration_ms' => $this->milliseconds($receipt->duration()),
            'hubs' => $receipt->hubs()->map(fn (ReceiptHub $hub) => [
                'name' => $hub->name(),
                'description' => $hub->description(),
                'skipped' => $hub->wasSkipped(),
                'skip_reason' => $hub->skipReason(),
                'duration_ms' => $this->milliseconds($hub->duration()),
                'steps' => $hub->steps()->map(fn (StepRecord $step) => [
                    'name' => $step->name(),
                    'rule' => $step->rule(),
                    'status' => $step->status(),
                    'duration_ms' => $this->milliseconds($step->duration()),
                    'messages' => $step->messages()->all(),
                    'origin' => $step->origin(),
                ])->all(),
            ])->all(),
        ];
    }

    protected function milliseconds(?float $duration): ?float
    {
        return $duration === null ? null : round($duration, 3);
    }
}
