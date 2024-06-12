<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Http\Twig\Twig;
use Psr\Http\Message\ResponseInterface;

class Killmail extends Controller
{
    public function __construct(
        protected \EK\Models\Killmails $killmails,
    ) {
        parent::__construct();
    }

    #[RouteAttribute('/killmail/count[/]', ['GET'])]
    public function count(): ResponseInterface
    {
        return $this->json([
            'count' => $this->killmails->count(),
        ]);
    }

    #[RouteAttribute('/killmail/{killmail_id:[0-9]+}[/]', ['GET'])]
    public function killmail(int $killmail_id): ResponseInterface
    {
        $killmail = $this->killmails->findOneOrNull(['killmail_id' => $killmail_id], ['projection' => ['_id' => 0]]);

        if ($killmail === null) {
            return $this->json([
                'error' => 'Killmail not found',
            ], 300);
        }

        return $this->json($this->cleanupTimestamps($killmail->toArray()));
    }

    #[RouteAttribute('/killmail[/]', ['POST'])]
    public function killmails(): ResponseInterface
    {
        $postData = json_validate($this->getBody()) ? json_decode($this->getBody(), true) : [];
        if (empty($postData)) {
            return $this->json(['error' => 'No data provided'], 300);
        }

        // Error if there are more than 1000 IDs
        if (count($postData) > 1000) {
            return $this->json(['error' => 'Too many IDs provided'], 300);
        }

        $killmails = $this->killmails->find(['killmail_id' => ['$in' => $postData]], ['projection' => ['_id' => 0]], 300)->map(function ($killmail) {
            return $this->cleanupTimestamps($killmail);
        });

        return $this->json($killmails->toArray(), 300);
    }
}
