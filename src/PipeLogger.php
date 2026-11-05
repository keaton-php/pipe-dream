<?php

namespace R1kon\PipeDream;

use Illuminate\Container\Container;
use InvalidArgumentException;
use R1kon\PipeDream\Contracts\PipeLoggerPersistStrategy;
use R1kon\PipeDream\Contracts\PipeLoggerStrategy;
use R1kon\PipeDream\Strategies\PipeLoggerJsonStrategy;

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

        $this->records[] = [
            'time' => microtime(true),
            'key' => $key,
            'type' => $type,
            'data' => $data,
            'context' => $context,
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
        if (! $key) {
            return collect($this->records)->groupBy('key');
        }

        return collect($this->records)->filter(function ($record) use ($key) {
            return $record['key'] == $key;
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
