<?php

namespace EK\Helpers;

use EK\Config\Config;
use EK\Fetchers\ESI;
use Eve\Sso\AuthenticationProvider;

class ESISSO
{
    public AuthenticationProvider $provider;

    public function __construct(
        protected Config $config,
        protected ESI $esi
    ) {
        $developmentMode = $this->config->get('development');

        $this->provider = new AuthenticationProvider([
            'clientId' => $this->config->get($developmentMode ? 'sso/dev/client_id' : 'sso/prod/client_id'),
            'clientSecret' => $this->config->get($developmentMode ? 'sso/dev/client_secret' : 'sso/prod/client_secret'),
            'redirectUri' => $this->config->get($developmentMode ? 'sso/dev/callback_url' : 'sso/prod/callback_url')
        ], [
            'publicData',
            'esi-killmails.read_corporation_killmails.v1',
            'esi-killmails.read_killmails.v1'
        ]);
    }

    public function getLoginUrl(): string
    {
        $_SESSION['state'] = $this->provider->generateState();
        return $this->provider->buildLoginUrl($_SESSION['state']);
    }
}
