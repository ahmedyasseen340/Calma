<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

/**
 * Reads one RSS / Atom feed, cleans the items, skips the ones we already have (same link),
 * puts the new ones in `articles` and links them to the matching categories.
 */
class ArticleImporter
{
    /** @return array{imported:int,skipped:int} */
    public function import(string $feedUrl, int $max = 20): array
    {
        $response = Http::timeout(20)->get($feedUrl);

        if (! $response->successful()) {
            throw new \RuntimeException("The feed answered with HTTP {$response->status()}.");
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body(), SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if (! $xml) {
            throw new \RuntimeException('The feed is not valid XML.');
        }

        $items = isset($xml->channel->item) ? $xml->channel->item : ($xml->entry ?? []);

        $imported = 0;
        $skipped = 0;
        $seen = 0;

        foreach ($items as $item) {
            if (++$seen > $max) {
                break;
            }

            $article = $this->readItem($item);

            if (! $article || Article::where('url', $article['url'])->exists()) {
                $skipped++;
                continue;
            }

            $categoryNames = $this->categoriesFor($article['title'].' '.$article['summary']);

            $model = Article::create($article);
            $model->categories()->sync(Category::whereIn('name', $categoryNames)->pluck('id')->all());
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /** @return array{title:string,summary:string,url:string,cover:?string}|null */
    private function readItem(SimpleXMLElement $item): ?array
    {
        $title = trim(html_entity_decode(strip_tags((string) $item->title)));
        $url = trim((string) $item->link);

        if ($url === '' && isset($item->link['href'])) {          // Atom: <link href="...">
            $url = trim((string) $item->link['href']);
        }

        $summaryRaw = (string) ($item->description ?? '');
        if (trim($summaryRaw) === '') {
            $summaryRaw = (string) ($item->summary ?? $item->content ?? '');
        }
        $summary = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($summaryRaw))));

        if ($title === '' || ! filter_var($url, FILTER_VALIDATE_URL) || strlen($url) > 255) {
            return null;
        }

        $cover = null;
        if (isset($item->enclosure['url']) && str_starts_with((string) $item->enclosure['type'], 'image')) {
            $cover = (string) $item->enclosure['url'];
        } else {
            $media = $item->children('http://search.yahoo.com/mrss/');
            if (isset($media->thumbnail)) {
                $cover = (string) $media->thumbnail->attributes()['url'];
            }
        }
        if ($cover !== null && (strlen($cover) > 255 || ! filter_var($cover, FILTER_VALIDATE_URL))) {
            $cover = null;
        }

        return [
            'title' => mb_substr($title, 0, 255),
            'summary' => $summary !== '' ? mb_substr($summary, 0, 1000) : $title,
            'url' => $url,
            'cover' => $cover,
        ];
    }

    /** @return list<string> */
    private function categoriesFor(string $text): array
    {
        $text = mb_strtolower($text);
        $names = [];

        foreach (config('articles.category_keywords') as $category => $words) {
            foreach ($words as $word) {
                if (str_contains($text, mb_strtolower($word))) {
                    $names[] = $category;
                    break;
                }
            }
        }

        return $names;
    }
}
