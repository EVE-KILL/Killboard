<?php

namespace EK\Controllers;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Http\Twig\Twig;
use EK\Models\Killmails;
use Psr\Http\Message\ResponseInterface;

class Index extends Controller
{
    public function __construct(
        protected Killmails $killmails,
        protected Twig $twig
    ) {
        parent::__construct($twig);
    }

    #[RouteAttribute('/', ['GET'])]
    public function index(): ResponseInterface
    {
        return $this->render('pages/frontpage.twig');
    }
}
