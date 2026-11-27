<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArticleImportTest extends TestCase
{
    use RefreshDatabase;

    private const RSS = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/">
  <channel>
    <title>Mental health</title>
    <item>
      <title>5 ways to handle ANXIETY at work</title>
      <link>https://site.test/anxiety-work</link>
      <description><![CDATA[<p>Simple <b>breathing</b> tips for stress.</p>]]></description>
      <enclosure url="https://site.test/img/anxiety.jpg" type="image/jpeg" length="1"/>
    </item>
    <item>
      <title>Better sleep and depression: what the research says</title>
      <link>https://site.test/sleep-depression</link>
      <description>Insomnia often goes with low mood.</description>
      <media:thumbnail url="https://site.test/img/sleep.jpg"/>
    </item>
    <item>
      <title>A gardening article</title>
      <link>https://site.test/garden</link>
      <description>Roses and tulips.</description>
    </item>
    <item>
      <title>No link here</title>
      <description>This item must be skipped.</description>
    </item>
  </channel>
</rss>
XML;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['anxiety', 'sleep', 'depression'] as $name) {
            Category::create(['name' => $name]);
        }
        config(['articles.feeds' => ['https://feed.test/rss']]);
    }

    public function test_it_imports_the_new_articles_cleans_them_and_picks_the_categories(): void
    {
        Http::fake(['feed.test/*' => Http::response(self::RSS, 200)]);

        $this->artisan('articles:fetch')->assertSuccessful();

        $this->assertDatabaseCount('articles', 3);   // the item without a link is skipped

        $anxiety = Article::where('url', 'https://site.test/anxiety-work')->first();
        $this->assertSame('Simple breathing tips for stress.', $anxiety->summary);   // html removed
        $this->assertSame('https://site.test/img/anxiety.jpg', $anxiety->cover);
        $this->assertSame(['anxiety'], $anxiety->categories->pluck('name')->all());

        $sleep = Article::where('url', 'https://site.test/sleep-depression')->first();
        $this->assertSame('https://site.test/img/sleep.jpg', $sleep->cover);
        $this->assertEqualsCanonicalizing(['sleep', 'depression'], $sleep->categories->pluck('name')->all());

        $garden = Article::where('url', 'https://site.test/garden')->first();
        $this->assertCount(0, $garden->categories);
    }

    public function test_running_it_again_does_not_duplicate_articles(): void
    {
        Http::fake(['feed.test/*' => Http::response(self::RSS, 200)]);

        $this->artisan('articles:fetch')->assertSuccessful();
        $this->artisan('articles:fetch')->assertSuccessful();

        $this->assertDatabaseCount('articles', 3);
    }

    public function test_a_broken_feed_does_not_stop_the_others(): void
    {
        config(['articles.feeds' => ['https://broken.test/rss', 'https://not-xml.test/rss', 'https://feed.test/rss']]);
        Http::fake([
            'broken.test/*' => Http::response('', 500),
            'not-xml.test/*' => Http::response('this is not xml at all', 200),
            'feed.test/*' => Http::response(self::RSS, 200),
        ]);

        $this->artisan('articles:fetch')->assertSuccessful();

        $this->assertDatabaseCount('articles', 3);
    }

    public function test_without_feeds_nothing_happens(): void
    {
        config(['articles.feeds' => []]);
        Http::fake();

        $this->artisan('articles:fetch')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_it_is_scheduled_every_day(): void
    {
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'articles:fetch'));

        $this->assertCount(1, $events);
    }
}
