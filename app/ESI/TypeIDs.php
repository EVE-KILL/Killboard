<?php

namespace EK\ESI;

use EK\Api\Abstracts\ESIInterface;

class TypeIDs extends ESIInterface
{
    public function __construct(
        protected \EK\Models\TypeIDs $typeIDs,
        protected EsiFetcher $esiFetcher
    ) {
        parent::__construct($esiFetcher);
    }

    public function getTypeInfo(int $type_id): ?array
    {
        $result = $this->fetch('/latest/universe/types/' . $type_id);
        ksort($result);
        $this->typeIDs->setData($result);
        $this->typeIDs->save();

        return $result;
    }
}
