<?php

namespace R1kon\PipeDream\Events;

class PipeDreamEvents
{
    protected $events;
    public function __construct($events = []) 
    {
        $this->events = $events;
    }

    public function addEvent($event)
    {
        $this->events[] = $event;
        return $this;
    }

    public function sendEventsWhen($when)
    {
        collect($this->events)->filter(function($event) use ($when) {
            return $event->whenToSend() === $when;
        })->each(function($event) {
            $event->sendEvent();
        });
    }

}