<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Fetchers\EveWho;
use EK\Meilisearch\Meilisearch;
use Illuminate\Support\Collection;
use EK\Models\Alliances;
use EK\Models\Corporations;
use EK\Models\Characters;
use EK\Models\Stations;
use EK\Models\Factions;
use EK\ESI\Alliances as ESIAlliances;
use EK\ESI\Corporations as ESICorporations;
use EK\ESI\Characters as ESICharacters;
use EK\ESI\Stations as ESIStations;
use EK\Logger\Logger;
use EK\RabbitMQ\RabbitMQ;
use League\Container\Container;
use MongoDB\BSON\UTCDateTime;

class UpdateCorporation extends Jobs
{
    protected string $defaultQueue = "corporation";

    public function __construct(
        protected Alliances $alliances,
        protected Corporations $corporations,
        protected Characters $characters,
        protected Stations $stations,
        protected Factions $factions,
        protected ESIAlliances $esiAlliances,
        protected ESICorporations $esiCorporations,
        protected ESICharacters $esiCharacters,
        protected ESIStations $esiStations,
        protected Meilisearch $meilisearch,
        protected EveWho $eveWhoFetcher,
        protected UpdateCharacter $updateCharacter,
        protected RabbitMQ $rabbitMQ,
        protected Logger $logger,
        protected Container $container,
    ) {
        parent::__construct($rabbitMQ, $logger);
    }

    public function handle(array $data): void
    {
        $corporationId = $data["corporation_id"];

        $corporationData = $this->fetchCorporationData($corporationId);
        $this->updateCorporationData($corporationData);
        $evewhoCorporationsJobs = $this->container->get(\EK\Jobs\EVEWhoCharactersInCorporation::class);
        $evewhoCorporationsJobs->enqueue(["corporation_id" => $corporationId]);
    }

    protected function fetchCorporationData($corporationId)
    {
        $corporation = $this->corporations->findOneOrNull(["corporation_id" => $corporationId]);

        $lastUpdated = $corporation->get('last_updated')?->toDateTime() ?? new \DateTime();
        if ($corporation === null || $lastUpdated < (new \DateTime())->modify('-14 day')) {
            $corporation = $this->esiCorporations->getCorporationInfo($corporationId);
        }

        return $corporation;
    }

    protected function updateCorporationData($corporationData)
    {
        $corporationData = $corporationData instanceof Collection ? $corporationData->toArray() : $corporationData;

        $corporationData["alliance_name"] = $this->fetchAllianceName($corporationData["alliance_id"] ?? 0);
        $corporationData["ceo_name"] = $this->fetchCharacterName($corporationData["ceo_id"] ?? 0);
        $corporationData["creator_name"] = $this->fetchCharacterName($corporationData["creator_id"] ?? 0);
        $corporationData["home_station_name"] = $this->fetchStationName($corporationData["home_station_id"] ?? 0);
        $corporationData["faction_name"] = $this->fetchFactionName($corporationData["faction_id"] ?? 0);
        $corporationData['last_updated'] = new UTCDateTime(time() * 1000);

        ksort($corporationData);

        $this->corporations->setData($corporationData);
        $this->corporations->save();

        $this->indexCorporationInSearch($corporationData);
    }

    protected function fetchAllianceName($allianceId)
    {
        if ($allianceId > 0) {
            $allianceData = $this->alliances->findOneOrNull(["alliance_id" => $allianceId]) ??
                            $this->esiAlliances->getAllianceInfo($allianceId);
            return $allianceData["name"] ?? "";
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

    protected function fetchStationName($stationId)
    {
        if ($stationId > 0) {
            $stationData = $this->stations->findOneOrNull(["station_id" => $stationId]) ??
                           $this->esiStations->getStationInfo($stationId);
            return $stationData["name"] ?? "";
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

    protected function indexCorporationInSearch($corporationData)
    {
        $this->meilisearch->addDocuments([
            "id" => $corporationData["corporation_id"],
            "name" => $corporationData["name"],
            "ticker" => $corporationData["ticker"],
            "type" => "corporation",
        ]);
    }
}
