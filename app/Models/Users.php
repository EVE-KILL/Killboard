<?php

namespace EK\Models;

use EK\Database\Collection;
use Illuminate\Support\Collection as SupportCollection;
use RuntimeException;

class Users extends Collection
{
    /** @var string Name of collection in database */
    public string $collectionName = 'users';

    /** @var string Name of database that the collection is stored in */
    public string $databaseName = 'app';

    /** @var string Primary index key */
    public string $indexField = 'email';

    /** @var string[] $hiddenFields Fields to hide from output (ie. Password hash, email etc.) */
    public array $hiddenFields = ['character_owner_hash', 'refresh_token', 'access_token', 'sso_expires', 'config'];

    /** @var string[] $required Fields required to insert data to model (ie. email, password hash, etc.) */
    public array $required = ['character_name', 'character_id'];

    /** @var string[] $indexes The fields that should be indexed */
    public array $indexes = [
        'unique' => ['character_name', 'character_id'],
        'desc' => ['identifier', 'last_fetched'],
        'asc' => [],
        'text' => []
    ];

    public function getUser(int $characterId): array
    {
        $currentTime = time();
        $user = $this->findOne(['character_id' => $characterId])->toArray();

        if ($user === null) {
            throw new RuntimeException('User not found');
        }

        if ($user['expiration'] < $currentTime) {
            throw new RuntimeException('User has expired');
        }

        return $user;
    }

    public function getUserByIdentifier(string $identifier): array
    {
        $currentTime = time();
        $user = $this->findOne(['identifier' => $identifier])->toArray();

        if ($user === null) {
            throw new RuntimeException('User not found');
        }

        if ($user['expiration'] < $currentTime) {
            throw new RuntimeException('User has expired');
        }

        return $user;
    }

    public function addUser(int $characterId, string $characterName, int $expiration, string $identifier): int
    {
        $user = $this->findOneOrNull(['character_id' => $characterId]);

        if ($user !== null) {
            throw new RuntimeException('User already exists');
        }

        $this->setData([
            'character_id' => $characterId,
            'character_name' => $characterName,
            'expiration' => $expiration,
            'identifier' => $identifier
        ]);

        return $this->save();
    }

    public function updateExpiration(int $characterId, int $expiration): bool
    {
        $user = $this->findOneOrNull(['character_id' => $characterId]);

        if ($user === null) {
            throw new RuntimeException('User not found');
        }

        $user['expiration'] = $expiration;
        $this->setData($user->toArray());
        return $this->save();
    }

    public function validateIdentifier(string $identifier): bool
    {
        $user = $this->findOneOrNull(['identifier' => $identifier]);

        if ($user === null) {
            throw new RuntimeException('User not found');
        }

        return true;
    }

    public function getUserConfig(string $identifier): SupportCollection
    {
        $user = $this->findOneOrNull(['identifier' => $identifier], showHidden: true);

        if ($user === null) {
            throw new RuntimeException('User not found');
        }

        return $user;
    }

    public function setUserConfig(string $identifier, array $config): bool
    {
        $user = $this->findOneOrNull(['identifier' => $identifier]);

        if ($user === null) {
            throw new RuntimeException('User not found');
        }

        $user['config'] = $config;
        $this->setData($user->toArray());
        return $this->save();
    }
}
