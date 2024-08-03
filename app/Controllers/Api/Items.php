<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Cache\Cache;
use EK\Models\Killmails;
use EK\Models\Prices;
use EK\Models\TypeIDs;
use Psr\Http\Message\ResponseInterface;

class Items extends Controller
{
    public function __construct(
        protected TypeIDs $typeIDs,
        protected Prices $prices,
        protected Killmails $killmails,
        protected Cache $cache
    ) {
    }

    #[RouteAttribute("/items[/]", ["GET"])]
    public function all(): ResponseInterface
    {
        $cacheKey = "items.all";
        if ($this->cache->exists($cacheKey)) {
            return $this->json(
                $this->cache->get($cacheKey),
                $this->cache->getTTL($cacheKey)
            );
        }

        $items = $this->typeIDs->find(
            [],
            ["projection" => [
                "_id" => 0,
                "dogma_attributes" => 0,
                "dogma_effects" => 0,
                "last_modified" => 0,
                "capacity" => 0,
                "icon_id" => 0,
                "market_group_id" => 0,
                "packaged_volume" => 0,
                "portion_size" => 0,
                "published" => 0,
                "radius" => 0,
                "volume" => 0,
            ]],
            0
        );

        $this->cache->set($cacheKey, $items, 3600);

        return $this->json($items);
    }

    #[RouteAttribute("/items/count[/]", ["GET"])]
    public function count(): ResponseInterface
    {
        return $this->json(["count" => $this->typeIDs->count()], 300);
    }

    #[RouteAttribute("/items/{item_id:[0-9]+}[/]", ["GET"])]
    public function item(int $item_id): ResponseInterface
    {
        $cacheKey = "items.{$item_id}";
        if ($this->cache->exists($cacheKey)) {
            return $this->json(
                $this->cache->get($cacheKey),
                $this->cache->getTTL($cacheKey)
            );
        }

        $item = $this->typeIDs->findOne(
            ["type_id" => $item_id],
            ["projection" => [
                "_id" => 0,
                "last_modified" => 0,
            ]],
            0
        );

        $this->cache->set($cacheKey, $item, 3600);

        return $this->json($item);
    }

    #[RouteAttribute("/items/{item_id:[0-9]+}/pricing[/{region_id:[0-9]+}[/{days:[0-9]+}]]", ["GET"])]
    public function pricing(int $item_id, int $days = 7, int $region_id = 10000002): ResponseInterface
    {
        $cacheKey = "items.{$item_id}.pricing.{$days}";
        if ($this->cache->exists($cacheKey)) {
            return $this->json(
                $this->cache->get($cacheKey),
                $this->cache->getTTL($cacheKey)
            );
        }

        $pricing = $this->prices->find([
            "type_id" => $item_id,
            "region_id" => $region_id,
            "date" => ['$gte' => new \MongoDB\BSON\UTCDateTime(strtotime("-{$days} days") * 1000)]
        ], [
            'projection' => [
                '_id' => 0,
                'last_modified' => 0,
            ],
            'sort' => ['date' => -1]
        ]);

        $pricing = $this->cleanupTimestamps($pricing->toArray());

        $this->cache->set($cacheKey, $pricing, 3600);

        return $this->json($pricing);
    }

    #[RouteAttribute("/items/{item_id:\d+}/killmails[/{limit:\d+}]", ["GET"])]
    public function killmails(int $item_id, int $limit = 100): ResponseInterface
    {
        $cacheKey = "items.{$item_id}.killmails";
        if ($this->cache->exists($cacheKey)) {
            return $this->json(
                $this->cache->get($cacheKey),
                $this->cache->getTTL($cacheKey)
            );
        }

        $killmails = $this->killmails->find([
            '$or' => [
                ['items.type_id' => $item_id],
                ['victim.ship_id' => $item_id]
            ]
        ], [
            'projection' => [
                '_id' => 0,
                'killmail_id' => 1
            ],
            'sort' => ['kill_time' => -1],
            'limit' => $limit
        ])->map(function ($killmail) {
            return $killmail['killmail_id'] ?? $killmail;
        });

        $this->cache->set($cacheKey, $killmails, 3600);

        return $this->json($killmails);
    }
}
