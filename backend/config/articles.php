<?php

return [
    // RSS / Atom feed addresses, separated by commas. Empty = nothing is imported.
    // Example in .env: ARTICLE_FEEDS=https://example.com/mental-health/feed,https://another.org/rss
    // Choose trusted sources together with the team (and check each site's terms of use).
    'feeds' => array_values(array_filter(array_map('trim', explode(',', (string) env('ARTICLE_FEEDS', ''))))),

    'max_per_feed' => (int) env('ARTICLE_MAX_PER_FEED', 20),

    // Secret for POST /api/internal/articles/fetch, so a free external scheduler (for example a GitHub Actions
    // schedule) can start the import on a host that has no cron. Empty = that address does not exist (404).
    'cron_token' => env('ARTICLE_CRON_TOKEN'),

    // An article gets a category when its title or summary contains one of these words (any case).
    'category_keywords' => [
        'anxiety' => ['anxiety', 'anxious', 'panic', 'worry', 'stress', 'phobia', 'قلق', 'توتر', 'خوف', 'رهاب'],
        'sleep' => ['sleep', 'insomnia', 'nightmare', 'bedtime', 'نوم', 'أرق', 'ارق'],
        'depression' => ['depression', 'depressed', 'sadness', 'hopeless', 'mood', 'اكتئاب', 'حزن'],
    ],
];
