<?php

namespace EK\Api;

use Illuminate\Support\Collection;
use MongoDB\BSON\UTCDateTime;
use MongoDB\DeleteResult;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;
use Traversable;

interface CollectionInterface
{
    public function find(
        array $filter = [],
        array $options = [],
        int $cacheTime = 60,
        bool $showHidden = false
    ): Collection;

    public function findOne(
        array $filter = [],
        array $options = [],
        int $cacheTime = 60,
        bool $showHidden = false
    ): Collection;

    public function aggregate(
        array $pipeline = [],
        array $options = []
    ): Traversable;

    public function count(
        array $filter = [],
        array $options = [],
        int $cacheTime = 60
    ): int;

    public function delete(array $filter = []): DeleteResult;

    public function update(array $filter = [], array $update = []): UpdateResult;

    public function truncate(): void;

    public function setData(array $data = []): void;

    public function getData(): Collection;

    public function saveMany(): void;

    public function save(): UpdateResult|InsertOneResult;

    public function clear(array $data = []): self;

    public function makeTimeFromDateTime(string $dateTime): UTCDateTime;

    public function makeTimeFromUnixTime(int $unixTime): UTCDateTime;

    public function makeTime(string|int $time): UTCDateTime;
}
