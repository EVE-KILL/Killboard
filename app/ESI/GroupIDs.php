<?php

namespace EK\ESI;

use EK\Api\Abstracts\ESIInterface;

class GroupIDs extends ESIInterface
{
    public function __construct(
        protected \EK\Models\GroupIDs $groupIDs,
        protected EsiFetcher $esiFetcher
    ) {
        parent::__construct($esiFetcher);
    }

    public function getGroupInfo(int $group_id): ?array
    {
        $result = $this->fetch('/latest/universe/groups/' . $group_id);
        ksort($result);
        $this->groupIDs->setData($result);
        $this->groupIDs->save();

        return $result;
    }
}
