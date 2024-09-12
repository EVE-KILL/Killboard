<?php

namespace EK\Controllers\Api;

use ArrayIterator;
use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Cache\Cache;
use EK\Helpers\Query as QueryHelper;
use EK\Models\Killmails;
use Psr\Http\Message\ResponseInterface;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;

class Query extends Controller
{
    public function __construct(
        protected Killmails $killmails,
        protected Cache $cache,
        protected QueryHelper $queryHelper,
    ) {
    }

    #[RouteAttribute('/query[/]', ['POST'], 'Query the API for killmails')]
    public function query(): ResponseInterface
    {
        $rawBody = $this->getBody();
        $postData = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = json_last_error_msg();
            return $this->json([
                "error" => "Invalid JSON provided",
                "details" => $errorMsg
            ], 400);
        }

        if (empty($postData)) {
            return $this->json(["error" => "No data provided"], 400);
        }

        $cacheKey = $this->cache->generateKey("query", $rawBody);
        $simpleOrComplexQuery = 'complex';
        if (isset($postData['type']) && !in_array($postData['type'], ['simple', 'complex'])) {
            return $this->json(["error" => "Invalid query type: " . $postData['type']], 400);
        }
        if (isset($postData['type']) && in_array($postData['type'], ['simple', 'complex'])) {
            $simpleOrComplexQuery = $postData['type'];
        }

        try {
            if ($simpleOrComplexQuery === 'complex') {
                $queryData = $this->queryHelper->generateComplexQuery($postData);
            } elseif ($simpleOrComplexQuery === 'simple') {
                $queryData = $this->queryHelper->generateSimpleQuery($postData);
            } else {
                return $this->json(["error" => "Invalid query type: " . $simpleOrComplexQuery], 400);
            }

            if ($this->cache->exists($cacheKey)) {
                $cachedData = $this->cache->get($cacheKey);
                $cachedData = array_map([$this, 'prepareQueryResult'], $cachedData);
                $cursor = new ArrayIterator($cachedData);
            } else {
                $cursor = $this->killmails->collection->aggregate($queryData['pipeline']);

                $results = iterator_to_array($cursor);
                $this->cache->set($cacheKey, $results, 300);

                $cursor = new ArrayIterator($results);
            }

            return $this->prepareAndStreamResultsWithPagination($cursor, $queryData['pagination']);
        } catch (InvalidArgumentException $e) {
            return $this->json(["error" => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(["error" => $e->getMessage()], 500);
        }
    }

    protected function prepareQueryResult(array $data): array
    {
        foreach ($data as $key => $value) {
            // Convert kill_time to Unix timestamp
            if ($key === 'kill_time') {
                if ($value instanceof UTCDateTime) {
                    $data[$key] = $value->toDateTime()->getTimestamp();
                } elseif (is_array($value) && isset($value['$date']['$numberLong'])) {
                    $data[$key] = (int)($value['$date']['$numberLong'] / 1000); // Convert milliseconds to seconds
                }
            }
            // Handle nested arrays
            elseif (is_array($value)) {
                $data[$key] = $this->prepareQueryResult($value);
            }
        }

        return $data;
    }

    protected function prepareAndStreamResultsWithPagination($cursor, array $pagination): ResponseInterface
    {
        $response = $this->response->withHeader('Content-Type', 'application/json');
        $body = $response->getBody();

        $paginationJson = json_encode([
            'pagination' => [
                'totalCount' => $pagination['totalCount'],
                'limit' => $pagination['limit'],
                'page' => $pagination['page']
            ]
        ]);

        $body->write(substr($paginationJson, 0, -1) . ',"killmails":[');
        $first = true;

        foreach ($cursor as $document) {
            $preparedDocument = $this->prepareQueryResult($document);
            if (!$first) {
                $body->write(',');
            }
            $body->write(json_encode($preparedDocument));
            $first = false;
        }

        $body->write(']}');

        return $response;
    }

    private function buildAggregatePipeline(array $query): array
    {
        $pipeline = [];

        // $match stage
        if (!empty($query['filter'])) {
            $pipeline[] = ['$match' => $query['filter']];
        }

        // Add any additional $match stages from involved_entities
        if (!empty($query['involved_entities_matches'])) {
            $pipeline = array_merge($pipeline, $query['involved_entities_matches']);
        }

        // $sort stage
        if (isset($query['options']['sort'])) {
            $pipeline[] = ['$sort' => $query['options']['sort']];
        }

        // $skip stage
        if (isset($query['options']['skip'])) {
            $pipeline[] = ['$skip' => $query['options']['skip']];
        }

        // $limit stage
        if (isset($query['options']['limit'])) {
            $pipeline[] = ['$limit' => $query['options']['limit']];
        }

        // $project stage
        if (isset($query['options']['projection'])) {
            $pipeline[] = ['$project' => $query['options']['projection']];
        }

        return $pipeline;
    }
}
