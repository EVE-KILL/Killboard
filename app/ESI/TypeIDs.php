<?php

namespace EK\ESI;

class TypeIDs
{
    public function __construct(
        protected \EK\Models\TypeIDs $typeIDs,
        protected EsiFetcher $esiFetcher
    ) {
    }

    public function getTypeInfo(int $type_id): ?array
    {
        $result = $this->esiFetcher->fetch('/latest/universe/types/' . $type_id);
        ksort($result);
        $this->typeIDs->setData($result);
        $this->typeIDs->save();

        return $result;
    }
}
