<?php

use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeRule;

class SendDraftsToPreview extends PipeRule
{
    public static $name = 'Send drafts to the preview';
    public static $description = 'Drafts are previewed, not published';

    public function sendDraftsToPreview($post)
    {
        if ($post->status === 'draft') {
            PipeRuleRedirect::toUrl("/preview/{$post->id}");
        }

        return self::SUCCESS;
    }
}
