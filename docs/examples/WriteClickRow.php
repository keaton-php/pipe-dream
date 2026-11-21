<?php

use Keaton\PipeDream\PipeRule;

class WriteClickRow extends PipeRule
{
    public function writeClickRow($click)
    {
        // Placeholder: your own rule would insert the click row here.
        $this->info('Row written');

        return self::SUCCESS;
    }
}
