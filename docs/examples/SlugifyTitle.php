<?php

use Keaton\PipeDream\PipeRule;

class SlugifyTitle extends PipeRule
{
    public static $name = 'Slugify the title';
    public static $description = 'Make the URL slug';

    public function slugifyTitle($post, Slugger $slugger)
    {
        $post->slug = $slugger->slug($post->title);

        return self::SUCCESS;
    }
}
