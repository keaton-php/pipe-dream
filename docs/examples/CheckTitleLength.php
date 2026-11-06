<?php

use Keaton\PipeDream\PipeRule;

class CheckTitleLength extends PipeRule
{
    public static $name = 'Check the title length';
    public static $description = 'Long titles get cut off in feeds';

    public function checkTitleLength($post)
    {
        if ($this->words < 2) {
            return $this->skip('A one-word title is fine');
        }

        if ($this->words > 12) {
            $this->error("The title has {$this->words} words");
        }

        return self::SUCCESS;
    }
}
