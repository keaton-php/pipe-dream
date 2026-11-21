<?php

use Keaton\PipeDream\PipeRule;

class ScoreDeviceFingerprint extends PipeRule
{
    public static $name = 'Score the device fingerprint';
    public static $description = 'A device we have seen commit fraud is a signal';

    public function scoreDeviceFingerprint($order)
    {
        return self::SUCCESS;
    }
}
