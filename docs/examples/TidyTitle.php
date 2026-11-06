<?php

use Keaton\PipeDream\PipeRule;

class TidyTitle extends PipeRule
{
    public static $name = 'Tidy the title';
    public static $description = 'Trim it, collapse the spaces, title-case it';

    public function tidyTitle($post)
    {
        $post->title = ucwords(strtolower(preg_replace('/\s+/', ' ', trim($post->title))));

        $this->info("Title is now \"{$post->title}\"");

        return self::SUCCESS;
    }
}
