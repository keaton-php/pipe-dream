<?php

namespace Keaton\PipeDream\Tests\Fixtures;

class Thermometer
{
    public function __construct(public int $degrees = 21)
    {
    }

    public function read(): int
    {
        return $this->degrees;
    }
}
