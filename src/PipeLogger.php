<?php

namespace R1kon\PipeDream;

use R1kon\PipeDream\PipeEnvelope;
use R1kon\PipeDream\Contracts\PipeLoggerPersistStrategy;

class PipeLogger 
{
    const INFO = 'info';
    const ERROR = 'error';
    const WARNING = 'warning';
    const FATAL = 'fatal';
    const TIMER_START = 'timerStart';
    const TIMER_END = 'timerEnd';
    const RETURN_VALUE = 'returnValue';

    protected $records = [];
    protected $outputStrategy;
    protected $persistLogsWith;
    protected $app;
    
    protected $level;

    public function __construct($app = null)
    {
        $this->app = $app ?? app();
    }

    public function persistLogsWith(PipeLoggerPersistStrategy $strategy)
    {
        return $this->persistLogsWith = $strategy;
    }

    public function persist(PipeEnvelope $subject, ?PipeLoggerPersistStrategy $strategy = null)
    {
        if($persistUsing = ($strategy ?? $this->persistLogsWith) ?? $this->getPersistDriverFromContainer())
            return $persistUsing->persist($subject);
    }

    protected function getPersistDriverFromContainer()
    {
        if( ! $this->app->bound(PipeLoggerPersistStrategy::class))
            return null;

        return $this->app->make(PipeLoggerPersistStrategy::class);
    }

    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
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

    protected function addRecord($key, $type, $data, $context = [])
    {
        $this->records[] = [
            'time' => microtime(true),
            'key' => $key,
            'type' => $type,
            'data' => $data,
            'context' => $context
        ];
    }

    public function recordsByType($type)
    {
        if(! is_array($type))
            $type = [ $type ];

        return collect($this->records)->filter(function($record) use ($type) {
            return in_array($record['type'], $type);
        });
    }

    public function setStrategy($strategy)
    {
        if(is_string($strategy) && class_exists($strategy))
            $strategy = new $strategy($this);
        elseif(! is_object($strategy))
            throw new \Exception("Invalid strategy provided");

        $this->outputStrategy = $strategy;
        return $this;
    }

    public function output($strategy = null)
    {
        if($strategy)
            $this->setStrategy($strategy);

        return $this->outputStrategy->output($this->records);
    }

    public function rebuild($dehydrated, $strategy = null)
    {
        if($strategy)
            $this->setStrategy($strategy);

        $this->records = $this->outputStrategy->rebuild($dehydrated);
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
        if(!$key)
            return collect($this->records)->groupBy('key');

        return collect($this->records)->filter(function($record) use ($key) {
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
        return $this->recordsByType([ self::TIMER_START, self::TIMER_END ]);
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
