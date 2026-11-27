<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArticleCronEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const RSS = '<?xml version="1.0"?><rss version="2.0"><channel><item><title>Better sleep tips</title><link>https://site.test/sleep</link><description>Sleep well.</description></item></channel></rss>';

    public function test_without_a_configured_token_the_address_does_not_exist(): void
    {
        config(['articles.cron_token' => null]);

        $this->postJson('/api/internal/articles/fetch', [], ['X-Cron-Token' => ''])->assertNotFound();
        $this->postJson('/api/internal/articles/fetch')->assertNotFound();
    }

    public function test_a_wrong_token_gets_404(): void
    {
        config(['articles.cron_token' => 'secret-token']);

        $this->postJson('/api/internal/articles/fetch', [], ['X-Cron-Token' => 'wrong'])->assertNotFound();
        $this->postJson('/api/internal/articles/fetch')->assertNotFound();
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_the_right_token_runs_the_import(): void
    {
        config(['articles.cron_token' => 'secret-token', 'articles.feeds' => ['https://feed.test/rss']]);
        Http::fake(['feed.test/*' => Http::response(self::RSS, 200)]);

        $this->postJson('/api/internal/articles/fetch', [], ['X-Cron-Token' => 'secret-token'])
            ->assertOk()->assertJsonStructure(['output']);

        $this->assertSame(1, Article::count());
    }
}
