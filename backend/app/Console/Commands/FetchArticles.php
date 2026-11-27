<?php

namespace App\Console\Commands;

use App\Services\ArticleImporter;
use Illuminate\Console\Command;
use Throwable;

class FetchArticles extends Command
{
    protected $signature = 'articles:fetch';

    protected $description = 'Fetch new mental health articles from the RSS feeds in ARTICLE_FEEDS and categorize them';

    public function handle(ArticleImporter $importer): int
    {
        $feeds = config('articles.feeds');

        if (empty($feeds)) {
            $this->warn('No feeds configured. Put the RSS addresses (separated by commas) in ARTICLE_FEEDS in .env.');

            return self::SUCCESS;
        }

        foreach ($feeds as $feed) {
            try {
                $result = $importer->import($feed, config('articles.max_per_feed'));
                $this->info("{$feed}: {$result['imported']} new, {$result['skipped']} skipped");
            } catch (Throwable $e) {
                // one broken feed must not stop the others
                $this->error("{$feed}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
