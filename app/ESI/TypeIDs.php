<?php

namespace EK\ESI;

use EK\Fetchers\ESI;
use EK\Models\TypeIDs as ModelsTypeIDs;

class TypeIDs
{
    public function __construct(
        protected ModelsTypeIDs $typeIDs,
        protected ESI $esiFetcher
    ) {
    }

    public function getTypeInfo(int $type_id): ?array
    {
        $result = $this->esiFetcher->fetch('/latest/universe/types/' . $type_id);
        $result = json_validate($result['body']) ? json_decode($result['body'], true) : [];
        ksort($result);
        $this->typeIDs->setData($result);
        $this->typeIDs->save();

        return $result;
    }
}
