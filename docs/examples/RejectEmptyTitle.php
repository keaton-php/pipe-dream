<?php

use Keaton\PipeDream\PipeRule;

class RejectEmptyTitle extends PipeRule
{
    public static $name = 'Reject empty titles';
    public static $description = 'A post needs a title';

    public function rejectEmptyTitle($post)
    {
        if (trim($post->title) === '') {
            $this->fatal('The post has no title');
        }

        return self::SUCCESS;
    }
}
