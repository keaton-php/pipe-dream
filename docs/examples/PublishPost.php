<?php

use Keaton\PipeDream\Exceptions\PipeRuleLoadView;
use Keaton\PipeDream\Exceptions\PipeRuleRedirect;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;

class PublishPost
{
    public function __invoke(object $post)
    {
        try {
            $envelope = PipeManager::create([
                PipeHub::create()
                    ->name('Check')
                    ->addPipes([RejectEmptyTitle::class, SendDraftsToPreview::class])
                    ->stopOnFatal(),

                PipeHub::create()
                    ->name('Publish')
                    ->addPipes([TidyTitle::class, SlugifyTitle::class]),
            ])
                ->name('Publish a post')
                ->subject($post)
                ->sendIt();
        } catch (PipeRuleRedirect $e) {
            return redirect($e->redirectTo);
        } catch (PipeRuleLoadView $e) {
            return view($e->view, $e->data);
        }

        // A fatal is not thrown: it is recorded on the envelope.
        if ($envelope->hasFatalErrors()) {
            return response()->json(['error' => 'This post cannot be published'], 422);
        }

        return redirect("/posts/{$post->slug}");
    }
}
