<?php

use Keaton\PipeDream\PipeRule;

class CountWords extends PipeRule
{
    public static $name = 'Count the words';
    public static $description = 'Remember how long the title is';

    public function countWords($post)
    {
        $this->words = str_word_count($post->title);

        return self::SUCCESS;
    }
}
