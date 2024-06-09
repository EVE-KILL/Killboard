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
        protected Twig $twig
    ) {
        parent::__construct($twig);
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
        $killmail = $this->killmails->findOneOrNull(['killmail_id' => $killmail_id]);

        if ($killmail === null) {
            return $this->json([
                'error' => 'Killmail not found',
            ], 300);
        }

        return $this->json($killmail);
    }
}
