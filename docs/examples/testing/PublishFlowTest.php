<?php

namespace Tests\Feature;

use Keaton\PipeDream\Exceptions\PipeRuleFatal;
use Keaton\PipeDream\PipeHub;
use Keaton\PipeDream\PipeManager;
use Keaton\PipeDream\PipeRule;
use RejectEmptyTitle;
use SlugifyTitle;
use TidyTitle;
use Tests\TestCase;

class PublishFlowTest extends TestCase
{
    private function publishFlow(object $post): PipeManager
    {
        return PipeManager::create([
            PipeHub::create()
                ->name('Check')
                ->addPipe(RejectEmptyTitle::class),

            PipeHub::create()
                ->name('Publish')
                ->skipIf(fn ($post) => $post->status === 'draft')
                ->addPipes([TidyTitle::class, SlugifyTitle::class]),
        ])
            ->name('Publish a post')
            ->subject($post);
    }

    public function test_a_ready_post_is_tidied_and_slugged(): void
    {
        $post = (object) ['title' => '  hello,   PIPE dream  ', 'status' => 'ready'];

        $envelope = $this->publishFlow($post)->sendIt();

        $this->assertFalse($envelope->hasFatalErrors());
        $this->assertSame('hello-pipe-dream', $post->slug);
        $this->assertCount(3, $envelope->results()->successes());
    }

    public function test_an_empty_title_stops_the_flow_and_nothing_after_it_runs(): void
    {
        $post = (object) ['title' => '', 'status' => 'ready'];

        $envelope = $this->publishFlow($post)->sendIt();

        $this->assertTrue($envelope->hasFatalErrors());
        $this->assertStringStartsWith('The post has no title', $envelope->logger()->fatals()->first()['data']);
        $this->assertNull($envelope->get(SlugifyTitle::class));
        $this->assertFalse(isset($post->slug));
    }

    public function test_a_draft_skips_the_publish_hub(): void
    {
        $post = (object) ['title' => 'Work in progress', 'status' => 'draft'];

        $envelope = $this->publishFlow($post)->sendIt();

        $this->assertSame(PipeRule::SKIP, $envelope->get(SlugifyTitle::class));
        $this->assertCount(2, $envelope->results()->skips());
        $this->assertFalse(isset($post->slug));
    }

    public function test_with_throw_fatals_the_stop_is_an_exception(): void
    {
        $this->expectException(PipeRuleFatal::class);

        $this->publishFlow((object) ['title' => '', 'status' => 'ready'])
            ->throwFatals()
            ->sendIt();
    }
}
