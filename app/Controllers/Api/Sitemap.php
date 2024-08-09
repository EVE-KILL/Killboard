<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Cache\Cache;
use EK\Models\Sitemap as SitemapModel;
use Psr\Http\Message\ResponseInterface;

class Sitemap extends Controller
{
    public function __construct(
        protected SitemapModel $sitemap,
        protected Cache $cache
    ) {
        parent::__construct();
    }

    #[RouteAttribute("/sitemap[/]", ["GET"], "Get all sitemap files")]
    public function all(): ResponseInterface
    {
        $cacheKey = $this->cache->generateKey('sitemap', 'all');

        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $this->json($cacheResult);
        }

        // A sitemap can only contain 50k URLs pr. sitemap file
        $sitemapCount = $this->sitemap->count();
        $numberOfSitemaps = ceil($sitemapCount / 50000);
        // Generate a $links array that contains links to /sitemap/x where x is the number of sitemap files
        $links = array_map(fn($i) => "/sitemap/{$i}", range(1, $numberOfSitemaps));

        $result = [
            'count' => $sitemapCount,
            'numberOfSitemaps' => $numberOfSitemaps,
            'links' => $links
        ];

        $this->cache->set($cacheKey, $result, 86400);

        return $this->json();
    }

    #[RouteAttribute("/sitemap/{page:[0-9]+}", ["GET"], "Get a sitemap file by page")]
    public function page(int $page): ResponseInterface
    {
        $page = $page - 1;
        $limit = 50000;
        $skip = $page * $limit;

        $cacheKey = $this->cache->generateKey('sitemap_page', $page, $limit, $skip);

        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $this->json($cacheResult);
        }

        $entries = $this->sitemap->collection->find([], [
            'projection' => ['_id' => 0, 'last_modified' => 0],
            'limit' => $limit,
            'skip' => $skip
        ])->toArray();

        $this->cache->set($cacheKey, $entries, 86400);

        return $this->json($entries);
    }
}
