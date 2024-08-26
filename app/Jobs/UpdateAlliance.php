<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Fetchers\EveWho;
use EK\Logger\FileLogger;
use EK\Meilisearch\Meilisearch;
use EK\RabbitMQ\RabbitMQ;
use Illuminate\Support\Collection;
use League\Container\Container;
use MongoDB\BSON\UTCDateTime;

class UpdateAlliance extends Jobs
{
    protected string $defaultQueue = "alliance";

    public function __construct(
        protected \EK\Models\Alliances $alliances,
        protected \EK\Models\Corporations $corporations,
        protected \EK\Models\Characters $characters,
        protected \EK\Models\Factions $factions,
        protected \EK\ESI\Alliances $esiAlliances,
        protected \EK\ESI\Corporations $esiCorporations,
        protected \EK\ESI\Characters $esiCharacters,
        protected Meilisearch $meilisearch,
        protected EveWho $eveWhoFetcher,
        protected UpdateCharacter $updateCharacter,
        protected RabbitMQ $rabbitMQ,
        protected FileLogger $logger,
        protected Container $container,
    ) {
        parent::__construct($rabbitMQ);
    }

    public function handle(array $data): void
    {
        $allianceId = $data["alliance_id"];

        $allianceData = $this->fetchAllianceData($allianceId);

        $this->updateAllianceData($allianceData);

        $evewhoAllianceJob = $this->container->get(EVEWhoCharactersInAlliance::class);
        $evewhoAllianceJob->enqueue(["alliance_id" => $allianceId]);
    }

    protected function fetchAllianceData($allianceId)
    {
        $alliance = $this->alliances->findOneOrNull(["alliance_id" => $allianceId]);

        $lastUpdated = $alliance->get('last_updated')?->toDateTime() ?? new \DateTime();
        if ($alliance === null || $lastUpdated < (new \DateTime())->modify('-14 day')) {
            $alliance = $this->esiAlliances->getAllianceInfo($allianceId);
        }

        return $alliance;
    }

    protected function updateAllianceData($allianceData)
    {
        $allianceData = $allianceData instanceof Collection ? $allianceData->toArray() : $allianceData;

        $allianceData["creator_corporation_name"] = $this->fetchCorporationName($allianceData["creator_corporation_id"]);
        $allianceData["executor_corporation_name"] = $this->fetchCorporationName($allianceData["executor_corporation_id"]);
        $allianceData["creator_name"] = $this->fetchCharacterName($allianceData["creator_id"]);
        $allianceData["faction_name"] = $this->fetchFactionName($allianceData["faction_id"] ?? 0);
        $allianceData["last_updated"] = new UTCDateTime(time() * 1000);

        ksort($allianceData);

        $this->alliances->setData($allianceData);
        $this->alliances->save();

        $this->indexAllianceInSearch($allianceData);
    }

    protected function fetchCorporationName($corporationId)
    {
        if ($corporationId > 0) {
            $corporationData = $this->corporations->findOneOrNull(["corporation_id" => $corporationId]) ??
                               $this->esiCorporations->getCorporationInfo($corporationId);
            return $corporationData["name"] ?? "";
        }
        return "";
    }

    protected function fetchCharacterName($characterId)
    {
        if ($characterId > 0) {
            $characterData = $this->characters->findOneOrNull(["character_id" => $characterId]) ??
                             $this->esiCharacters->getCharacterInfo($characterId);
            return $characterData["name"] ?? "";
        }
        return "";
    }

    protected function fetchFactionName($factionId)
    {
        if ($factionId > 0) {
            $factionData = $this->factions->findOne(["faction_id" => $factionId]);
            return $factionData["name"] ?? "";
        }
        return "";
    }

    protected function indexAllianceInSearch($allianceData)
    {
        $this->meilisearch->addDocuments([
            "id" => $allianceData["alliance_id"],
            "name" => $allianceData["name"],
            "ticker" => $allianceData["ticker"],
            "type" => "alliance",
        ]);
    }
}
