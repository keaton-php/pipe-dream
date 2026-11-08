<?php

namespace Keaton\PipeDream;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Keaton\PipeDream\Contracts\PipeLoggerPersistStrategy;
use Keaton\PipeDream\Contracts\PipeLoggerStrategy;
use Keaton\PipeDream\Strategies\PipeLoggerJsonStrategy;
use Keaton\PipeDream\Support\LogValue;

/**
 * The audit trail of one run: an ordered list of records, each with a time,
 * a key (usually the rule class), a type and data.
 */
class PipeLogger
{
    const INFO = 'info';
    const ERROR = 'error';
    const WARNING = 'warning';
    const FATAL = 'fatal';
    const TIMER_START = 'timerStart';
    const TIMER_END = 'timerEnd';
    const RETURN_VALUE = 'returnValue';

    /** Record everything (the default). */
    const LEVEL_EVERYTHING = 'everything';

    /** Record everything except rule timers. */
    const LEVEL_NO_TIMERS = 'no_timers';

    /** Record only warnings, errors and fatals. */
    const LEVEL_PROBLEMS = 'problems';

    const LEVELS = [self::LEVEL_EVERYTHING, self::LEVEL_NO_TIMERS, self::LEVEL_PROBLEMS];

    protected $records = [];

    protected ?PipeLoggerStrategy $outputStrategy = null;

    protected ?PipeLoggerPersistStrategy $persistLogsWith = null;

    protected $app;

    protected string $level = self::LEVEL_EVERYTHING;

    public function __construct($app = null)
    {
        $this->app = $app ?? Container::getInstance();
    }

    public function persistLogsWith(PipeLoggerPersistStrategy $strategy)
    {
        return $this->persistLogsWith = $strategy;
    }

    /**
     * Persist with the given strategy, else the one set with persistLogsWith(),
     * else the one bound to PipeLoggerPersistStrategy in the container. With
     * none of those it does nothing and returns null.
     */
    public function persist(PipeEnvelope $subject, ?PipeLoggerPersistStrategy $strategy = null)
    {
        if ($persistUsing = $strategy ?? $this->persistLogsWith ?? $this->getPersistDriverFromContainer()) {
            return $persistUsing->persist($subject);
        }

        return null;
    }

    protected function getPersistDriverFromContainer()
    {
        if (! $this->app || ! $this->app->bound(PipeLoggerPersistStrategy::class)) {
            return null;
        }

        return $this->app->make(PipeLoggerPersistStrategy::class);
    }

    public function setLevel($level)
    {
        if (! in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException(
                "Unknown log level [{$level}]. Use one of PipeLogger::LEVEL_EVERYTHING, LEVEL_NO_TIMERS or LEVEL_PROBLEMS."
            );
        }

        $this->level = $level;

        return $this;
    }

    public function level(): string
    {
        return $this->level;
    }

    public function records()
    {
        return $this->records;
    }

    public function setRecords($records)
    {
        $this->records = $records;

        return $this;
    }

    /**
     * The run as a list of steps: one StepRecord per rule (or closure pipe)
     * that executed or was skipped, in run order.
     *
     * Steps are computed from the records, so they read the same live or
     * rebuilt from the database. Each is anchored on the rule's result record,
     * carries the hub it ran in, its timing (when timers are on) and the lines
     * it logged. Hub-level log lines are not steps and never appear here.
     *
     * @return Collection<int, StepRecord>
     */
    public function steps(): Collection
    {
        $records = array_values($this->records);
        $count = count($records);

        // The hub a step ran in is the last hub that announced itself before
        // the step. Hub and manager lines carry the hub's name in context.
        $hubAtIndex = [];
        $currentHub = null;

        foreach ($records as $i => $record) {
            if (strpos((string) ($record['key'] ?? ''), 'PipeHub:') === 0) {
                $name = $record['context']['name'] ?? null;

                if ($name !== null && $name !== '') {
                    $currentHub = $name;
                }
            }

            $hubAtIndex[$i] = $currentHub;
        }

        // Each rule writes exactly one result record; that is a step's anchor.
        $anchors = [];

        foreach ($records as $i => $record) {
            if (($record['type'] ?? null) === self::RETURN_VALUE && ! $this->isHubKey((string) ($record['key'] ?? ''))) {
                $anchors[] = $i;
            }
        }

        $steps = new Collection();
        $usedStart = [];
        $usedEnd = [];

        foreach ($anchors as $position => $anchor) {
            $record = $records[$anchor];
            $key = (string) $record['key'];

            // Messages belong to the step whose result they sit beside: between
            // the previous anchor and the next, and logged under this key. (A
            // rule's own error line is written just after its result record.)
            $lower = $position > 0 ? $anchors[$position - 1] : -1;
            $upper = $position + 1 < count($anchors) ? $anchors[$position + 1] : $count;

            $messages = [];

            for ($j = $lower + 1; $j < $upper; $j++) {
                if ($j === $anchor || (string) ($records[$j]['key'] ?? '') !== $key) {
                    continue;
                }

                if (in_array($records[$j]['type'] ?? '', [self::INFO, self::WARNING, self::ERROR, self::FATAL], true)) {
                    $messages[] = (string) ($records[$j]['data'] ?? '');
                }
            }

            $steps->push(new StepRecord(
                $key,
                $hubAtIndex[$anchor],
                $record['data'] ?? null,
                $this->durationFor($records, $anchor, $key, $usedStart, $usedEnd),
                $messages
            ));
        }

        return $steps;
    }

    /**
     * The milliseconds between the step's timer start and end, pairing each
     * timer once so the same rule run twice is timed separately. Null when the
     * timers are not in the log (LEVEL_NO_TIMERS).
     */
    protected function durationFor(array $records, int $anchor, string $key, array &$usedStart, array &$usedEnd): ?float
    {
        $start = null;
        $end = null;

        for ($j = $anchor - 1; $j >= 0; $j--) {
            if (($records[$j]['type'] ?? null) === self::TIMER_START && (string) $records[$j]['key'] === $key && empty($usedStart[$j])) {
                $start = $j;
                break;
            }
        }

        for ($j = $anchor + 1, $n = count($records); $j < $n; $j++) {
            if (($records[$j]['type'] ?? null) === self::TIMER_END && (string) $records[$j]['key'] === $key && empty($usedEnd[$j])) {
                $end = $j;
                break;
            }
        }

        if ($start === null || $end === null) {
            return null;
        }

        $usedStart[$start] = true;
        $usedEnd[$end] = true;

        return max(0.0, ((float) $records[$end]['time'] - (float) $records[$start]['time']) * 1000);
    }

    /**
     * Whether a key belongs to the hub or manager itself, not to a rule.
     */
    protected function isHubKey(string $key): bool
    {
        return strpos($key, 'PipeHub:') === 0 || strpos($key, 'PipeManager:') === 0;
    }

    protected function shouldRecord(string $type): bool
    {
        switch ($this->level) {
            case self::LEVEL_PROBLEMS:
                return in_array($type, [self::WARNING, self::ERROR, self::FATAL], true);
            case self::LEVEL_NO_TIMERS:
                return ! in_array($type, [self::TIMER_START, self::TIMER_END], true);
            default:
                return true;
        }
    }

    protected function addRecord($key, $type, $data, $context = [])
    {
        if (! $this->shouldRecord($type)) {
            return;
        }

        // Records are kept JSON-safe so persisting a log can never fail.
        $this->records[] = [
            'time' => microtime(true),
            'key' => (string) $key,
            'type' => $type,
            'data' => LogValue::normalize($data),
            'context' => LogValue::normalize($context),
        ];
    }

    public function recordsByType($type)
    {
        $types = (array) $type;

        return collect($this->records)->filter(function ($record) use ($types) {
            return in_array($record['type'], $types, true);
        });
    }

    /**
     * @param  PipeLoggerStrategy|class-string<PipeLoggerStrategy>  $strategy
     */
    public function setStrategy($strategy)
    {
        if (is_string($strategy) && class_exists($strategy)) {
            $strategy = new $strategy();
        }

        if (! $strategy instanceof PipeLoggerStrategy) {
            throw new InvalidArgumentException('A log strategy must implement '.PipeLoggerStrategy::class.'.');
        }

        $this->outputStrategy = $strategy;

        return $this;
    }

    public function strategy(): PipeLoggerStrategy
    {
        return $this->outputStrategy ??= new PipeLoggerJsonStrategy();
    }

    /**
     * The records in the strategy's format (JSON unless told otherwise).
     */
    public function output($strategy = null)
    {
        if ($strategy) {
            $this->setStrategy($strategy);
        }

        return $this->strategy()->output($this->records);
    }

    /**
     * Load records previously produced by output().
     */
    public function rebuild($dehydrated, $strategy = null)
    {
        if ($strategy) {
            $this->setStrategy($strategy);
        }

        $this->records = $this->strategy()->rebuild($dehydrated);

        return $this;
    }

    public function infos()
    {
        return $this->recordsByType(self::INFO);
    }

    public function errors()
    {
        return $this->recordsByType(self::ERROR);
    }

    public function returnValues()
    {
        return $this->recordsByType(self::RETURN_VALUE);
    }

    public function recordsByKey($key = null)
    {
        // Only null means "group them all": 0, '0' and '' are keys too.
        if ($key === null) {
            return collect($this->records)->groupBy('key');
        }

        return collect($this->records)->filter(function ($record) use ($key) {
            return (string) $record['key'] === (string) $key;
        });
    }

    public function warnings()
    {
        return $this->recordsByType(self::WARNING);
    }

    public function fatals()
    {
        return $this->recordsByType(self::FATAL);
    }

    public function timers()
    {
        return $this->recordsByType([self::TIMER_START, self::TIMER_END]);
    }

    public function info($key, $data, $context = [])
    {
        $this->addRecord($key, self::INFO, $data, $context);
    }

    public function error($key, $data, $context = [])
    {
        $this->addRecord($key, self::ERROR, $data, $context);
    }

    public function warning($key, $data, $context = [])
    {
        $this->addRecord($key, self::WARNING, $data, $context);
    }

    public function fatal($key, $data, $context = [])
    {
        $this->addRecord($key, self::FATAL, $data, $context);
    }

    public function returnValue($key, $data, $context = [])
    {
        $this->addRecord($key, self::RETURN_VALUE, $data, $context);
    }

    public function timerStart($key, $message, $context = [])
    {
        $this->addRecord($key, self::TIMER_START, $message, $context);
    }

    public function timerEnd($key, $message, $context = [])
    {
        $this->addRecord($key, self::TIMER_END, $message, $context);
    }
}
