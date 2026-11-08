<?php

namespace Keaton\PipeDream;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Keaton\PipeDream\Contracts\ReceiptFormat;
use Keaton\PipeDream\Contracts\ReceiptSummary;
use Keaton\PipeDream\Formats\ConsoleFormat;
use Stringable;

/**
 * What one run did, hub by hub and step by step, read from its log.
 *
 * A receipt holds the data; a ReceiptFormat decides how it looks. It reads
 * the same for a run that just happened (`$envelope->receipt()`) and one
 * rebuilt from the database (`$log->receipt()`). Echo it for the default
 * format (ConsoleFormat, unless one is bound to ReceiptFormat).
 */
class Receipt implements Stringable
{
    const COMPLETED = 'completed';
    const COMPLETED_WITH_ERRORS = 'completed with errors';
    const HANDED_OFF = 'handed off';
    const STOPPED = 'stopped';

    /** The key a subject's ReceiptSummary is recorded under when a run ends. */
    const SUMMARY_KEY = 'PipeDream:summary';

    protected ?Collection $steps = null;

    protected ?Collection $hubs = null;

    public function __construct(
        protected PipeLogger $logger,
        protected ?string $flowName = null,
        protected ?string $runId = null,
        protected mixed $subject = null
    ) {
    }

    /**
     * What the run meant in business terms: the subject's ReceiptSummary,
     * as recorded when the run ended (or asked now, if it was not). Null
     * when the subject has none; formats then show outcome().
     */
    public function summary(): ?string
    {
        $recorded = $this->logger->infos()->where('key', self::SUMMARY_KEY)->last();

        if ($recorded !== null) {
            return (string) $recorded['data'];
        }

        if ($this->subject instanceof ReceiptSummary) {
            $summary = $this->subject->receiptSummary();

            return $summary === '' ? null : $summary;
        }

        return null;
    }

    /**
     * The flow's name: the one given, else the one its manager logged.
     */
    public function flowName(): ?string
    {
        if ($this->flowName !== null) {
            return $this->flowName;
        }

        foreach ($this->logger->records() as $record) {
            if (strpos((string) ($record['key'] ?? ''), 'PipeManager:') === 0 && ! empty($record['context']['name'])) {
                return $record['context']['name'];
            }
        }

        return null;
    }

    public function runId(): ?string
    {
        return $this->runId;
    }

    public function logger(): PipeLogger
    {
        return $this->logger;
    }

    /**
     * @return Collection<int, StepRecord>
     */
    public function steps(): Collection
    {
        return $this->steps ??= $this->logger->steps();
    }

    /**
     * The hubs the run reached, in order, each with its steps.
     *
     * @return Collection<int, ReceiptHub>
     */
    public function hubs(): Collection
    {
        if ($this->hubs !== null) {
            return $this->hubs;
        }

        $groups = $this->steps()
            ->chunkWhile(fn (StepRecord $step, $key, Collection $chunk) => $chunk->last()->hub() === $step->hub())
            ->map(fn (Collection $chunk) => $chunk->values())
            ->values();

        $hubs = new Collection();
        $next = 0;

        if ($groups->isNotEmpty() && $groups[0]->first()->hub() === null) {
            $hubs->push(new ReceiptHub(null, null, $groups[$next++]));
        }

        foreach ($this->announcedHubs() as $hub) {
            $steps = isset($groups[$next]) && $groups[$next]->first()->hub() === $hub['name']
                ? $groups[$next++]
                : new Collection();

            $hubs->push(new ReceiptHub($hub['name'], $hub['description'], $steps, $hub['skipped']));
        }

        for (; $next < $groups->count(); $next++) {
            $hubs->push(new ReceiptHub($groups[$next]->first()->hub(), null, $groups[$next]));
        }

        return $this->hubs = $hubs;
    }

    /**
     * How the run ended: STOPPED by a fatal, HANDED_OFF to a redirect or
     * view, COMPLETED_WITH_ERRORS, or COMPLETED.
     */
    public function outcome(): string
    {
        return match (true) {
            $this->wasStopped() => self::STOPPED,
            $this->wasHandedOff() => self::HANDED_OFF,
            $this->hasErrors() => self::COMPLETED_WITH_ERRORS,
            default => self::COMPLETED,
        };
    }

    public function wasStopped(): bool
    {
        return $this->logger->fatals()->isNotEmpty();
    }

    /**
     * Whether anything recorded an error, or any step ended in FAIL.
     */
    public function hasErrors(): bool
    {
        return $this->logger->errors()->isNotEmpty() || $this->steps()->contains(fn (StepRecord $step) => $step->hasErrors());
    }

    public function wasHandedOff(): bool
    {
        foreach ($this->logger->records() as $record) {
            $data = is_string($record['data'] ?? null) ? $record['data'] : '';

            if (str_starts_with($data, 'Flow handed off') || str_starts_with($data, 'Redirecting to ') || str_starts_with($data, 'Loading view ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The run's total time in milliseconds, first record to last.
     */
    public function duration(): ?float
    {
        $records = array_values($this->logger->records());

        if (count($records) < 2) {
            return null;
        }

        return max(0.0, ((float) end($records)['time'] - (float) $records[0]['time']) * 1000);
    }

    /**
     * The receipt in the given format, or the default one.
     */
    public function format(?ReceiptFormat $format = null): mixed
    {
        return ($format ?? static::defaultFormat())->format($this);
    }

    /**
     * Echo the receipt in the given format, or the default one.
     */
    public function print(?ReceiptFormat $format = null): void
    {
        echo $this->asString($this->format($format));
    }

    /**
     * The format bound to ReceiptFormat in the container, else ConsoleFormat.
     */
    public static function defaultFormat(): ReceiptFormat
    {
        $app = Container::getInstance();

        return $app->bound(ReceiptFormat::class) ? $app->make(ReceiptFormat::class) : new ConsoleFormat();
    }

    public function __toString(): string
    {
        return $this->asString($this->format());
    }

    protected function asString(mixed $output): string
    {
        if (is_string($output) || $output instanceof Stringable) {
            return (string) $output;
        }

        return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    /**
     * Each hub the log names, in the order it ran.
     *
     * @return array<int, array{name: string, description: ?string, skipped: bool}>
     */
    protected function announcedHubs(): array
    {
        $hubs = [];

        foreach ($this->logger->records() as $record) {
            $name = $record['context']['name'] ?? null;

            if (strpos((string) ($record['key'] ?? ''), 'PipeHub:') !== 0 || $name === null || $name === '') {
                continue;
            }

            $last = count($hubs) - 1;

            if ($last < 0 || $hubs[$last]['name'] !== $name) {
                $hubs[] = ['name' => $name, 'description' => $record['context']['description'] ?? null, 'skipped' => false];
                $last++;
            }

            if (($record['data'] ?? null) === 'Skipping hub') {
                $hubs[$last]['skipped'] = true;
            }
        }

        return $hubs;
    }
}
