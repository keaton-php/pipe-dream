<?php

namespace Tests\Feature;

use CheckTitleLength;
use CountWords;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeRule;
use Slugger;
use SlugifyTitle;
use TidyTitle;
use Tests\TestCase;

class TidyTitleTest extends TestCase
{
    public function test_it_tidies_the_title_and_says_so(): void
    {
        $post = (object) ['title' => '  hello,   PIPE dream  '];

        $envelope = PipeHub::create()
            ->addPipe(TidyTitle::class)
            ->sendIt($post);

        $this->assertSame('Hello, Pipe Dream', $post->title);
        $this->assertSame(PipeRule::SUCCESS, $envelope->get(TidyTitle::class));
        $this->assertFalse($envelope->hasErrors());
        $this->assertSame(
            'Title is now "Hello, Pipe Dream"',
            $envelope->logger()->infos()->firstWhere('key', TidyTitle::class)['data']
        );
    }

    public function test_a_one_word_title_skips_the_length_check(): void
    {
        $envelope = PipeHub::create()
            ->addPipes([CountWords::class, CheckTitleLength::class])
            ->sendIt((object) ['title' => 'Hello']);

        $this->assertSame(PipeRule::SKIP, $envelope->get(CheckTitleLength::class));
    }

    public function test_the_slug_comes_from_whatever_the_container_provides(): void
    {
        $this->app->bind(Slugger::class, fn () => new class extends Slugger {
            public function slug(string $text): string
            {
                return 'a-fixed-slug';
            }
        });

        $post = (object) ['title' => 'Anything at all'];

        PipeHub::create()->addPipe(SlugifyTitle::class)->sendIt($post);

        $this->assertSame('a-fixed-slug', $post->slug);
    }
}
