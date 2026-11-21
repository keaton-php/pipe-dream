<?php

use Keaton\PipeDream\PipeRule;

class FirePostback extends PipeRule
{
    public function firePostback($click)
    {
        // Placeholder: your own rule would call the advertiser's postback URL here.
        $this->info('Advertiser notified, HTTP 200');

        return self::SUCCESS;
    }
}
