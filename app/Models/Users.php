<?php

namespace EK\Models;

use EK\Database\Collection;
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
    public array $hiddenFields = [];

    /** @var string[] $required Fields required to insert data to model (ie. email, password hash, etc.) */
    public array $required = ['email', 'password'];

    /** @var string[] $indexes The fields that should be indexed */
    public array $indexes = [
        'unique' => ['email'],
        'desc' => [],
        'asc' => [],
        'text' => []
    ];

    public function addUser(string $email, string $password): bool
    {
        $userExists = $this->findOne(['email' => $email]);
        if ($userExists) {
            throw new RuntimeException('User already exists');
        }

        $this->data->add([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        $saveResult = $this->save();
        if ($saveResult->getInsertedCount() >= 1 && $saveResult->isAcknowledged()) {
            return true;
        }

        return false;
    }

    public function deleteUser(string $email): bool
    {
        $user = $this->find(['email' => $email]);
        if (!$user) {
            throw new RuntimeException('User does not exist');
        }

        $deleteResult = $this->delete(['email' => $email]);
        if ($deleteResult->getDeletedCount() >= 1 && $deleteResult->isAcknowledged()) {
            return true;
        }
    }

    public function validUser(string $email, string $password, string $passwordConfirm): bool
    {
        if ($password !== $passwordConfirm) {
            throw new RuntimeException('Passwords do not match');
        }

        $user = $this->find(['email' => $email]);
        if (!$user) {
            throw new RuntimeException('User does not exist');
        }

        if (password_verify($password, $user->password)) {
            return true;
        }

        throw new RuntimeException('Password is incorrect');
    }
}
