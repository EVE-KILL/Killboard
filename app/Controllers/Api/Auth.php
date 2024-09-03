<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Cache\Cache;
use EK\Config\Config;
use EK\Helpers\ESISSO;
use EK\Models\Users;
use Psr\Http\Message\ResponseInterface;

class Auth extends Controller
{
    public function __construct(
        protected ESISSO $sso,
        protected Cache $cache,
        protected Config $config,
        protected Users $users
    ) {
        parent::__construct();
    }

    #[RouteAttribute("/auth/eve/getloginurl[/]", ["GET"], "Get the EVE SSO login URL")]
    public function getLoginUrl(): ResponseInterface
    {
        return $this->json([
            'url' => $this->sso->getLoginUrl()
        ]);
    }

    #[RouteAttribute("/auth/eve[/]", ["GET"], "EVE SSO Callback")]
    public function callback(): ResponseInterface
    {
        $auth = $this->sso->getProvider()->validateAuthenticationV2($this->getParam('state'), $this->getParam('state'), $this->getParam('code'));

        $characterId = $auth->getCharacterId();
        $characterName = $auth->getCharacterName();
        $characterOwnerHash = $auth->getCharacterOwnerHash();
        $refreshToken = $auth->getToken()->getRefreshToken();
        $accessToken = $auth->getToken()->getToken();
        $expires = $auth->getToken()->getExpires();

        // Return a unique hash that the frontend can use to authenticate, this hash should be stored in the cache with a TTL of 5 seconds
        $loginHash = base64_encode(json_encode([
            'characterId' => $characterId,
            'characterName' => $characterName
        ]));

        $this->cache->set($loginHash, json_encode([
            'character_id' => $characterId,
            'character_name' => $characterName
        ]), 5);

        $this->users->setData([
            'character_id' => $characterId,
            'character_name' => $characterName,
            'character_owner_hash' => $characterOwnerHash,
            'refresh_token' => $refreshToken,
            'access_token' => $accessToken,
            'sso_expires' => $expires
        ]);
        $this->users->save();

        $baseUriFrontend = $this->config->get('base_uri/' . ($this->config->get('development') ? 'frontend/dev' : 'frontend/prod'));
        return $this->redirect($baseUriFrontend . '/auth/eve/success?hash=' . $loginHash);
    }

    #[RouteAttribute("/auth/login/{hash}[/]", ["GET"], "Login with a hash")]
    public function login(string $hash): ResponseInterface
    {
        $character = $this->cache->get($hash);
        if ($character === null) {
            return $this->json([
                'error' => 'Invalid hash'
            ], status: 401);
        }

        $data = json_decode($character, true);
        $data['expiration'] = time() + 3600;
        $data['identifier'] = uniqid('', true);
        $this->users->setData($data);
        $this->users->save();


        return $this->json($data);
    }

    #[RouteAttribute("/auth/reauth/{identifier}[/]", ["GET"], "Reauthenticate with an identifier")]
    public function reauth(string $identifier): ResponseInterface
    {
        $user = $this->users->findOneOrNull(['identifier' => $identifier], ['projection' => [
            '_id' => 0,
            'last_modified' => 0
        ]], 0);

        if ($user === null) {
            return $this->json([
                'error' => 'Invalid identifier'
            ], status: 401);
        }

        $user['expiration'] = time() + 3600;
        $user['identifier'] = uniqid('', true);
        $this->users->setData($user->toArray());
        $this->users->save();

        return $this->json($user);
    }
}
