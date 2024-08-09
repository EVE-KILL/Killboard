<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Meilisearch\Meilisearch;
use Psr\Http\Message\ResponseInterface;

class Search extends Controller
{
    public function __construct(protected Meilisearch $meilisearch)
    {
        parent::__construct();
    }

    #[RouteAttribute("/search/{searchParam}[/]", ["GET"], "Search for a string")]
    public function search(string $searchParam): ResponseInterface
    {
        $results = $this->meilisearch->search($searchParam);

        return $this->json([
            "query" => $results->getQuery(),
            "hits" => $results->getHits(),
        ]);
    }

    #[RouteAttribute("/search[/]", ["POST"], "Search for multiple strings")]
    public function searchPost(): ResponseInterface
    {
        $postData = json_validate($this->getBody())
            ? json_decode($this->getBody(), true)
            : [];
        if (empty($postData)) {
            return $this->json(["error" => "No data provided"], 300);
        }

        // Error if there are more than 1000 IDs
        if (count($postData) > 1000) {
            return $this->json(
                ["error" => "Too many search params provided"],
                300
            );
        }

        $results = [];
        foreach ($postData as $searchParam) {
            $result = $this->meilisearch->search($searchParam);
            $results[] = [
                "query" => $result->getQuery(),
                "hits" => $result->getHits(),
            ];
        }

        return $this->json($results);
    }
}
