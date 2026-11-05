<?php
namespace R1kon\PipeDream\Events;

class PipeDreamEvent
{
    protected $event;
    protected $when;
    
    public function __construct($event) 
    {
        $this->event = $event;
    }

    public function sendEventWhen($when)
    {
        $this->when = $when;
        return $this;
    }

    public function send()
    {
        dispatch($this->event);
    }
}