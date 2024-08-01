<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Meilisearch\Meilisearch;
use EK\Models\Alliances;
use EK\Models\Characters;
use EK\Models\Corporations;

class UpdateMeilisearch extends ConsoleCommand
{
    protected string $signature = "update:meilisearch";
    protected string $description = "Updates the search index in Meilisearch";

    public function __construct(
        protected Alliances $alliances,
        protected Corporations $corporations,
        protected Characters $characters,
        protected Meilisearch $meilisearch,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    final public function handle(): void
    {
        $alliances = $this->alliances->find(
            [],
            [
                "projection" => [
                    "_id" => 1,
                    "name" => 1,
                    "alliance_id" => 1,
                    "ticker" => 1,
                ],
            ]
        );
        $corporations = $this->corporations->find(
            [],
            [
                "projection" => [
                    "_id" => 1,
                    "name" => 1,
                    "corporation_id" => 1,
                    "ticker" => 1,
                ],
            ]
        );
        $characters = $this->characters->find(
            [],
            ["projection" => ["_id" => 1, "name" => 1, "character_id" => 1]]
        );

        $this->out("Found " . count($alliances) . " alliances");
        $this->out("Found " . count($corporations) . " corporations");
        $this->out("Found " . count($characters) . " characters");

        $documents = [];
        foreach ($alliances as $alliance) {
            $documents[] = [
                "id" => $alliance["alliance_id"],
                "name" => $alliance["name"],
                "ticker" => $alliance["ticker"],
                "type" => "alliance",
            ];
        }

        foreach ($corporations as $corporation) {
            $documents[] = [
                "id" => $corporation["corporation_id"],
                "name" => $corporation["name"],
                "ticker" => $corporation["ticker"],
                "type" => "corporation",
            ];
        }

        foreach ($characters as $character) {
            $documents[] = [
                "id" => $character["character_id"],
                "name" => $character["name"],
                "type" => "character",
            ];
        }

        $this->out("Adding " . count($documents) . " documents to Meilisearch");

        // Insert in chunks of 1000
        $progressBar = $this->progressBar(count($documents));
        $chunkedDocuments = array_chunk($documents, 1000);

        foreach ($chunkedDocuments as $chunk) {
            $this->meilisearch->addDocuments($chunk);
            $progressBar->advance(count($chunk));
        }

        $progressBar->finish();
    }
}
