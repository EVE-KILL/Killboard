<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Models\Alliances;
use EK\Models\Characters;
use EK\Models\Corporations;
use EK\Models\Regions;
use EK\Models\Sitemap;
use EK\Models\SolarSystems;
use EK\Models\TypeIDs;

class UpdateSitemap extends Cronjob
{
    protected string $cronTime = '0 2 * * *';

    public function __construct(
        protected Alliances $alliances,
        protected Corporations $corporations,
        protected Characters $characters,
        protected TypeIDs $typeIDs,
        protected SolarSystems $solarSystems,
        protected Regions $regions,
        protected Sitemap $sitemap
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->logger->info('Updating sitemap...');

        // Initialize sitemap array
        $sitemapEntries = [];

        // Helper function to add entries to the sitemap
        $addToSitemap = function ($cursor, $urlPrefix, $priority, $idField, $nameField, $imageUrlPrefix, $imageSuffix = 'logo') use (&$sitemapEntries) {
            foreach ($cursor as $document) {
                $imageUrl = $imageUrlPrefix === 'https://eve-kill.com/map.png'
                            ? $imageUrlPrefix
                            : "{$imageUrlPrefix}/{$document[$idField]}/{$imageSuffix}";

                $sitemapEntries[] = [
                    'loc' => "{$urlPrefix}/{$document[$idField]}",
                    'lastmod' => $document['last_modified']->toDateTime()->format('c'),
                    'changefreq' => 'weekly',
                    'priority' => $priority,
                    'name' => $document[$nameField] ?? '',
                    'image' => $imageUrl
                ];
            }
        };

        // Generate URLs and add to sitemap using cursors
        $alliancesCursor = $this->alliances->collection->find([], ['projection' => ['_id' => 0, 'alliance_id' => 1, 'name' => 1, 'last_modified' => 1]]);
        $addToSitemap($alliancesCursor, '/alliance', '0.5', 'alliance_id', 'name', 'https://images.evetech.net/alliances');

        $corporationsCursor = $this->corporations->collection->find([], ['projection' => ['_id' => 0, 'corporation_id' => 1, 'name' => 1, 'last_modified' => 1]]);
        $addToSitemap($corporationsCursor, '/corporation', '0.7', 'corporation_id', 'name', 'https://images.evetech.net/corporations');

        $charactersCursor = $this->characters->collection->find([], ['projection' => ['_id' => 0, 'character_id' => 1, 'name' => 1, 'last_modified' => 1]]);
        $addToSitemap($charactersCursor, '/character', '1.0', 'character_id', 'name', 'https://images.evetech.net/characters', 'portrait');

        $typeIDsCursor = $this->typeIDs->collection->find(['published' => true], ['projection' => ['_id' => 0, 'type_id' => 1, 'name' => 1, 'last_modified' => 1]]);
        $addToSitemap($typeIDsCursor, '/item', '0.3', 'type_id', 'name', 'https://images.evetech.net/types', 'icon');

        $solarSystemsCursor = $this->solarSystems->collection->find([], ['projection' => ['_id' => 0, 'system_id' => 1, 'name' => 1, 'last_modified' => 1]]);
        $addToSitemap($solarSystemsCursor, '/system', '0.6', 'system_id', 'name', 'https://eve-kill.com/map.png', '');

        $regionsCursor = $this->regions->collection->find([], ['projection' => ['_id' => 0, 'region_id' => 1, 'name' => 1, 'last_modified' => 1]]);
        $addToSitemap($regionsCursor, '/region', '0.4', 'region_id', 'name', 'https://eve-kill.com/map.png', '');

        $sitemapCount = count($sitemapEntries);
        $this->logger->info('Saving ' . $sitemapCount . ' entries to the database...');

        // Function to chunk array into smaller arrays
        $chunkArray = function ($array, $size) {
            $chunks = [];
            for ($i = 0; $i < count($array); $i += $size) {
                $chunks[] = array_slice($array, $i, $size);
            }
            return $chunks;
        };

        // Chunk the sitemap entries into batches of 10,000
        $sitemapChunks = $chunkArray($sitemapEntries, 100000);

        // Insert each chunk into the database
        foreach ($sitemapChunks as $chunk) {
            $this->sitemap->setData($chunk);
            $this->sitemap->saveMany();
        }

        $this->logger->info('Sitemap updated successfully!');
    }
}
