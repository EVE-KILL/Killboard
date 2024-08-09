<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Models\Users;
use Psr\Http\Message\ResponseInterface;

class UserConfig extends Controller
{
    public function __construct(
        protected Users $users
    ) {
        parent::__construct();
    }

    #[RouteAttribute("/config/get/{identifier}[/]", ["GET"], "Get user config by identifier")]
    public function getConfigForIdentifier(string $identifier): ResponseInterface
    {

    }
}
