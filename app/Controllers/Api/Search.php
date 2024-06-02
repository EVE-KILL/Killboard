<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Http\Twig\Twig;
use EK\Meilisearch\Meilisearch;
use Psr\Http\Message\ResponseInterface;

class Search extends Controller
{
    public function __construct(
        protected Meilisearch $meilisearch,
        protected Twig $twig
    ) {
        parent::__construct($twig);
    }

    #[RouteAttribute('/search/{searchParam}[/]', ['GET'])]
    public function search(string $searchParam): ResponseInterface
    {
        $results = $this->meilisearch->search($searchParam);

        return $this->json([
            'query' => $results->getQuery(),
            'hits' => $results->getHits(),
        ]);
    }
}
