<?php

namespace EK\Commands\Updates;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Models\Celestials;
use EK\Models\Factions;
use EK\Models\InvFlags;
use GuzzleHttp\Client;

class UpdateSDE extends ConsoleCommand
{
    protected string $signature = 'update:sde { --force : Force an update }';
    protected string $description = 'Updates various collections with data from the latest SDE made by Fuzzysteve';
    protected Client $client;

    public function __construct(
        protected Celestials $celestials,
        protected InvFlags   $invFlags,
        protected Factions   $factions,
        ?string              $name = null
    )
    {
        parent::__construct($name);
        $this->client = new Client();
    }

    final public function handle(): void
    {
        ini_set('memory_limit', '-1');

        $sdeSqlite = 'https://www.fuzzwork.co.uk/dump/sqlite-latest.sqlite.bz2';
        $sdeSqliteMd5 = 'https://www.fuzzwork.co.uk/dump/sqlite-latest.sqlite.bz2.md5';

        $cachePath = BASE_DIR . '/cache';

        // Fetch the md5 and compare it to a locally stored one
        $this->out('<info>Fetching MD5</info>');
        $response = $this->client->request('GET', $sdeSqliteMd5);
        $md5 = $response->getBody()->getContents();
        $md5matches = false;
        $localMd5 = file_exists("{$cachePath}/sqlite-latest.sqlite.bz2.md5") ?
            file_get_contents("{$cachePath}/sqlite-latest.sqlite.bz2.md5") :
            null;

        if ($md5 === $localMd5 && $this->force === false) {
            $md5matches = true;
            $this->out('<info>MD5 is the same, skipping</info>');
            return;
        }

        // Check if the SQLite file already exists
        if ((!file_exists("{$cachePath}/sqlite-latest.sqlite") && $md5matches === false) || $this->force === true) {
            // Download the SDE
            $this->out('<info>Downloading SDE</info>');
            if (file_exists("{$cachePath}/sqlite-latest.sqlite.bz2")) {
                unlink("{$cachePath}/sqlite-latest.sqlite.bz2");
            }
            $response = $this->client->request('GET', $sdeSqlite, ['sink' => "{$cachePath}/sqlite-latest.sqlite.bz2"]);
            exec("bzip2 -d {$cachePath}/sqlite-latest.sqlite.bz2");
        }

        // Open the SDE
        $pdo = new \PDO('sqlite:' . $cachePath . '/sqlite-latest.sqlite');

        // Update celestials
        $this->updateCelestials($pdo);
        $this->updateInvFlags($pdo);
        $this->updateFactions($pdo);

    }

    private function updateFactions(\PDO $pdo): void
    {
        $stmt = $pdo->query('SELECT
            `chrFactions`.`factionID` AS `faction_id`,
            `chrFactions`.`factionName` AS `name`,
            `chrFactions`.`description` AS `description`,
            `chrFactions`.`raceIDs` AS `race_ids`,
            `chrFactions`.`solarSystemID` AS `solar_system_id`,
            `chrFactions`.`corporationID` AS `corporation_id`,
            `chrFactions`.`sizeFactor` AS `size_factor`,
            `chrFactions`.`stationCount` AS `station_count`,
            `chrFactions`.`stationSystemCount` AS `station_system_count`,
            `chrFactions`.`militiaCorporationID` AS `militia_corporation_id`,
            `chrFactions`.`iconID` AS `icon_id` from `chrFactions`'
        );

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $bigInsert = [];
        foreach ($result as $faction) {
            $bigInsert[] = [
                'faction_id' => (int)$faction['faction_id'],
                'name' => $faction['name'],
                'description' => $faction['description'],
                'race_ids' => $faction['race_ids'],
                'solar_system_id' => (int)$faction['solar_system_id'],
                'corporation_id' => (int)$faction['corporation_id'],
                'size_factor' => (float)$faction['size_factor'],
                'station_count' => (int)$faction['station_count'],
                'station_system_count' => (int)$faction['station_system_count'],
                'militia_corporation_id' => (int)$faction['militia_corporation_id'],
                'icon_id' => (int)$faction['icon_id'],
            ];
        }

        $this->out('<info>Inserting/Updating ' . count($bigInsert) . ' factions</info>');
        $this->factions->setData($bigInsert);
        $this->factions->saveMany();
    }

    private function updateInvFlags(\PDO $pdo): void
    {
        $stmt = $pdo->query('SELECT
            `invFlags`.`flagID` AS `flag_id`,
            `invFlags`.`flagName` AS `flag_name`,
            `invFlags`.`flagText` AS `flag_text`,
            `invFlags`.`orderID` AS `order_id` from `invFlags`'
        );

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $bigInsert = [];
        foreach ($result as $flag) {
            $bigInsert[] = [
                'flag_id' => (int)$flag['flag_id'],
                'flag_name' => $flag['flag_name'],
                'flag_text' => $flag['flag_text'],
                'order_id' => (int)$flag['order_id'],
            ];
        }

        $this->out('<info>Inserting/Updating ' . count($bigInsert) . ' invFlags</info>');
        $this->invFlags->setData($bigInsert);
        $this->invFlags->saveMany();
    }

    private function updateCelestials(\PDO $sqlite): void
    {
        $stmt = $sqlite->query('SELECT
            `mapDenormalize`.`itemID` AS `item_id`,
            `mapDenormalize`.`itemName` AS `item_name`,
            `invTypes`.`typeName` AS `type_name`,
            `mapDenormalize`.`typeID` AS `type_id`,
            `mapSolarSystems`.`solarSystemName` AS `solar_system_name`,
            `mapDenormalize`.`solarSystemID` AS `solar_system_id`,
            `mapDenormalize`.`constellationID` AS `constellation_id`,
            `mapDenormalize`.`regionID` AS `region_id`,
            `mapRegions`.`regionName` AS `region_name`,
            `mapDenormalize`.`orbitID` AS `orbit_id`,
            `mapDenormalize`.`x` AS `x`,
            `mapDenormalize`.`y` AS `y`,
            `mapDenormalize`.`z` AS `z` from
            ((((`mapDenormalize`
                join `invTypes` on((`mapDenormalize`.`typeID` = `invTypes`.`typeID`)))
                join `mapSolarSystems` on((`mapSolarSystems`.`solarSystemID` = `mapDenormalize`.`solarSystemID`)))
                join `mapRegions` on((`mapDenormalize`.`regionID` = `mapRegions`.`regionID`)))
                join `mapConstellations` on((`mapDenormalize`.`constellationID` = `mapConstellations`.`constellationID`))
            )'
        );

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Generate a big insert
        $seenItemIDs = [];
        $bigInsert = [];

        foreach ($result as $celestial) {
            if (in_array($celestial['item_id'], $seenItemIDs)) {
                continue;
            }

            $bigInsert[] = [
                'item_id' => (int)$celestial['item_id'],
                'item_name' => $celestial['item_name'],
                'type_name' => $celestial['type_name'],
                'type_id' => (int)$celestial['type_id'],
                'solar_system_name' => $celestial['solar_system_name'],
                'solar_system_id' => (int)$celestial['solar_system_id'],
                'constellation_id' => (int)$celestial['constellation_id'],
                'region_id' => (int)$celestial['region_id'],
                'region_name' => $celestial['region_name'],
                'orbit_id' => (int)$celestial['orbit_id'],
                'x' => (float)$celestial['x'],
                'y' => (float)$celestial['y'],
                'z' => (float)$celestial['z'],
            ];
        }

        $this->out('<info>Inserting/Updating ' . count($bigInsert) . ' celestials</info>');
        $this->celestials->setData($bigInsert);
        $this->celestials->saveMany();
    }
}
