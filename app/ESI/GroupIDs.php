<?php

namespace EK\ESI;

use EK\Fetchers\ESI;

class GroupIDs
{
    public function __construct(
        protected \EK\Models\GroupIDs $groupIDs,
        protected ESI $esiFetcher
    ) {
    }

    public function getGroupInfo(int $group_id): ?array
    {
        $result = $this->esiFetcher->fetch('/latest/universe/groups/' . $group_id);
        $result = json_validate($result['body']) ? json_decode($result['body'], true) : [];
        ksort($result);
        $this->groupIDs->setData($result);
        $this->groupIDs->save();

        return $result;
    }
}
