<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Http\Twig\Twig;
use EK\Jobs\validateProxy;
use EK\Models\Proxies;
use Psr\Http\Message\ResponseInterface;

class Proxy extends Controller
{
    public function __construct(
        protected Proxies $proxiesModel,
        protected validateProxy $validateProxy
    ) {
        parent::__construct();
    }
    #[RouteAttribute('/proxy/list[/]', ['GET'])]
    public function listAll(): ResponseInterface
    {
        $proxies = $this->proxiesModel->find();
        return $this->json($proxies->toArray());
    }

    #[RouteAttribute('/proxy/add[/]', ['GET', 'POST'])]
    public function add(): ResponseInterface
    {
        if ($this->request->getMethod() === 'GET') {
            return $this->json(['error' => 'Method not allowed!'], status: 405);
        }

        $data = json_decode($this->getPostData(), true);

        $this->validator->add([
            'id' => 'required',
            'url' => 'required',
            'owner' => 'required'
        ]);

        if (!$this->validator->validate($data)) {
            return $this->json(['error' => 'Invalid data!'], status: 400);
        }

        $this->proxiesModel->setData([
            'proxy_id' => $data['id'],
            'url' => rtrim($data['url'], '/'),
            'owner' => $data['owner'],
        ]);
        $this->proxiesModel->save();

        $this->validateProxy->enqueue(['proxy_id' => $data['id']], 'high');

        return $this->json(["status" => "success", "data" => $this->proxiesModel->getData()]);
    }

    #[RouteAttribute('/proxy/[{proxy_id}]', ['GET'])]
    public function listByName(?string $proxy_id = null): ResponseInterface
    {
        $proxies = $this->proxiesModel->findOne(['proxy_id' => $proxy_id]);
        return $this->json($proxies->toArray());
    }
}
