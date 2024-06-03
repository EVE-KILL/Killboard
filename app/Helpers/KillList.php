<?php

namespace EK\Helpers;

use EK\Models\Killmails;

class KillList
{
    public function __construct(
        protected Killmails $killmails,
    ) {

    }

    public function getLatest(int $page = 1, int $limit = 100): array
    {
        $offset = $limit * ($page - 1);
        $data = $this->killmails->find([], [
            'hint' => ['kill_time' => -1], // This is a hint to use the index 'killmail_time_-1
            'sort' => ['kill_time' => -1],
            'projection' => ['_id' => 0, 'items' => 0],
            'skip' => $offset,
            'limit' => $limit
        ]);

        return $data->toArray();
    }
}