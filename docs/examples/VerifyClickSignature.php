<?php

use Keaton\PipeDream\PipeRule;

class VerifyClickSignature extends PipeRule
{
    public function verifyClickSignature($click)
    {
        // Placeholder: your own rule would verify the click's signature here.

        return self::SUCCESS;
    }
}
