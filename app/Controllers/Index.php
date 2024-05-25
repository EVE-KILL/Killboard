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

    #[RouteAttribute('/test.json', ['GET'])]
    public function testjson(): ResponseInterface
    {
        throw new \Exception('test');
        return $this->json(['message' => 'Hello World!']);
    }

    #[RouteAttribute('/test', ['GET'])]
    public function test(): ResponseInterface
    {
        //$killmail = $this->killmails->findOne();
        // Get a random killmail
        $killmail = $this->killmails->getRandom();
        return $this->json($killmail->toArray() ?? []);
    }

    #[RouteAttribute('/[{name}]', ['GET'])]
    public function index(?string $name = 'Moo'): ResponseInterface
    {
        return $this->render('index.twig', ['name' => $name]);
    }
}
