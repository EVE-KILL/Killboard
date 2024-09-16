<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Helpers\ESISSO;
use EK\Jobs\ProcessESI;
use EK\Logger\StdOutLogger;
use EK\Models\Characters;
use EK\Models\Users;
use League\OAuth2\Client\Token\AccessToken;

class KillmailFetch extends Cronjob
{
    protected string $cronTime = '* * * * *';

    public function __construct(
        protected StdOutLogger $logger,
        protected Users $usersModel,
        protected Characters $charactersModel,
        protected ESISSO $esiSSO,
        protected ProcessESI $processESI
    ) {
        parent::__construct($logger);
    }

    public function handle(): void
    {
        $users = $this->usersModel->find([
            'last_fetched' => ['$lt' => new \MongoDB\BSON\UTCDateTime(strtotime('-5 minutes') * 1000)]
        ]);

        foreach ($users as $user) {
            $accessToken = $user['access_token'];
            $refreshToken = $user['refresh_token'];
            $expires = $user['sso_expires'];
            $characterId = $user['character_id'];
            $corporation = $this->charactersModel->findOne(['character_id' => $characterId]);
            $corporationId = $corporation['corporation_id'];
            $fetchCorporation = true;
            if ($corporationId < 10000000) {
                $fetchCorporation = false;
            }

            $provider = $this->esiSSO->getProvider();
            $existingToken = new AccessToken([
                'refresh_token' => $refreshToken,
                'access_token' => $accessToken,
                'expires' => $expires
            ]);

            try {
                $token = $provider->refreshAccessToken($existingToken);
                $accessToken = $token->getToken();
                $refreshToken = $token->getRefreshToken();
                $expires = $token->getExpires();

                $user['access_token'] = $accessToken;
                $user['refresh_token'] = $refreshToken;
                $user['sso_expires'] = $expires;

                $this->usersModel->setData($user);
                $this->usersModel->save();

                // Queue the job
                $this->processESI->enqueue([
                    'access_token' => $accessToken,
                    'character_id' => $characterId,
                    'corporation_id' => $corporationId,
                    'fetch_corporation' => $fetchCorporation
                ]);

                // Update the users last_fetched field
                $this->usersModel->collection->updateOne(
                    ['character_id' => $characterId],
                    ['$set' => ['last_fetched' => new \MongoDB\BSON\UTCDateTime(time() * 1000)]]
                );

            } catch (\Exception $e) {
                dump("Updating user failed: {$user['character_name']} - {$e->getMessage()}");
                $this->usersModel->delete(['character_id' => $characterId]);
            }
        }
    }
}
