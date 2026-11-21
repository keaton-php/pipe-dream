<?php

use Keaton\PipeDream\PipeRule;

class HeadlessBrowserSignals extends PipeRule
{
    public function headlessBrowserSignals($click)
    {
        if (str_contains($click->userAgent, 'Headless')) {
            $click->addBotSignal('Headless browser');
            $this->warning('webdriver flag set, no viewport');

            return self::FAIL;
        }

        return self::SUCCESS;
    }
}
