<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Logger\StdOutLogger;
use EK\Meilisearch\Meilisearch as MeilisearchClient;
use EK\Models\Alliances;
use EK\Models\Characters;
use EK\Models\Corporations;
use EK\Models\Regions;
use EK\Models\SolarSystems;
use EK\Models\TypeIDs;

class Meilisearch extends Cronjob
{
    protected string $cronTime = '0 0 * * *';

    public function __construct(
        protected Alliances $alliances,
        protected Corporations $corporations,
        protected Characters $characters,
        protected TypeIDs $typeIDs,
        protected SolarSystems $solarSystems,
        protected Regions $regions,
        protected MeilisearchClient $meilisearch,
        protected StdOutLogger $logger
    ) {
        parent::__construct($logger);
    }

    public function handle(): void
    {
        $this->logger->info("Updating Meilisearch index");

        $alliances = $this->alliances->find([
            "name" => ['$ne' => ""],
        ], [
            "projection" => [
                "_id" => 0,
                "name" => 1,
                "alliance_id" => 1,
                "ticker" => 1,
            ],
        ]);
        $corporations = $this->corporations->find([
            "name" => ['$ne' => ""],
        ], [
            "projection" => [
                "_id" => 0,
                "name" => 1,
                "corporation_id" => 1,
                "ticker" => 1,
            ],
        ]);
        $characters = $this->characters->find(
            [
                "name" => ['$ne' => ""],
                "deleted" => false,
            ],
            ["projection" => ["_id" => 0, "name" => 1, "character_id" => 1]]
        );
        $items = $this->typeIDs->find(
            [
                "name" => ['$ne' => ""],
                "published" => true,
            ],
            ["projection" => ["_id" => 0, "name" => 1, "type_id" => 1]]
        );
        $systems = $this->solarSystems->find(
            [
                "name" => ['$ne' => ""],
            ],
            ["projection" => ["_id" => 0, "name" => 1, "system_id" => 1]]
        );
        $regions = $this->regions->find(
            [
                "name" => ['$ne' => ""],
            ],
            ["projection" => ["_id" => 0, "name" => 1, "region_id" => 1]]
        );

        $documents = [];
        foreach ($alliances as $alliance) {
            if (empty($alliance["name"])) {
                continue;
            }

            $documents[] = [
                "id" => $alliance["alliance_id"],
                "name" => $alliance["name"],
                "ticker" => $alliance["ticker"] ?? "",
                "type" => "alliance",
            ];
        }

        foreach ($corporations as $corporation) {
            if (empty($corporation["name"])) {
                continue;
            }

            $documents[] = [
                "id" => $corporation["corporation_id"],
                "name" => $corporation["name"],
                "ticker" => $corporation["ticker"] ?? "",
                "type" => "corporation",
            ];
        }

        foreach ($characters as $character) {
            if (empty($character["name"])) {
                continue;
            }

            $documents[] = [
                "id" => $character["character_id"],
                "name" => $character["name"],
                "type" => "character",
            ];
        }

        foreach ($items as $item) {
            $documents[] = [
                "id" => $item["type_id"],
                "name" => $item["name"],
                "type" => "item",
            ];
        }

        foreach ($systems as $system) {
            $documents[] = [
                "id" => $system["system_id"],
                "name" => $system["name"],
                "type" => "system",
            ];
        }

        foreach ($regions as $region) {
            $documents[] = [
                "id" => $region["region_id"],
                "name" => $region["name"],
                "type" => "region",
            ];
        }

        $this->logger->info("Adding " . count($documents) . " documents to Meilisearch");

        // Insert in chunks of 1000
        $chunkedDocuments = array_chunk($documents, 1000);

        foreach ($chunkedDocuments as $chunk) {
            $this->meilisearch->addDocuments($chunk);
        }

        $this->logger->info("Meilisearch update complete");
    }
}
