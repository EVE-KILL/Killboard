<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Cache\Cache;
use EK\Helpers\Battle;
use EK\Models\Celestials;
use EK\Models\Comments;
use EK\Models\Killmails;
use EK\Models\KillmailsESI;
use Psr\Http\Message\ResponseInterface;

class Killmail extends Controller
{
    public function __construct(
        protected Killmails $killmails,
        protected KillmailsESI $killmailsESI,
        protected Comments $comments,
        protected Celestials $celestials,
        protected Battle $battleHelper,
        protected Cache $cache
    ) {
        parent::__construct();
    }

    #[RouteAttribute("/killmail/count[/]", ["GET"], "Get the count of all killmails")]
    public function count(): ResponseInterface
    {
        return $this->json([
            "count" => $this->killmails->aproximateCount(),
        ]);
    }

    #[RouteAttribute("/killmail/{killmail_id:[0-9]+}[/]", ["GET"], "Get a killmail by ID")]
    public function killmail(int $killmail_id): ResponseInterface
    {
        $killmail = $this->killmails->findOneOrNull(
            ["killmail_id" => $killmail_id],
            ["projection" => ["_id" => 0]]
        );

        if ($killmail === null) {
            return $this->json(
                [
                    "error" => "Killmail not found",
                ],
                300
            );
        }

        $killmail = $this->cleanupTimestamps($killmail);

        // Add comment count to the killmail
        $commentCount = $this->comments->count(['identifier' => 'kill:' . $killmail['killmail_id']]);
        $killmail['comment_count'] = $commentCount;

        return $this->json($killmail);
    }

    #[RouteAttribute("/killmail/esi/{killmail_id:[0-9]+}[/]", ["GET"], "Get esi killmail by ID")]
    public function esi(int $killmail_id): ResponseInterface
    {
        $killmail = $this->killmailsESI->findOneOrNull(
            ["killmail_id" => $killmail_id],
            ["projection" => ["_id" => 0, "killmail_time_str" => 0]]
        );
        if ($killmail === null) {
            return $this->json(
                [
                    "error" => "Killmail not found",
                ],
                300
            );
        }

        return $this->json($this->cleanupTimestamps($killmail));
    }

    #[RouteAttribute("/killmail/{killmail_id:[0-9]+}/inbattle[/]", ["GET"], "Check if a killmail is in a battle")]
    public function inBattle(int $killmail_id): ResponseInterface
    {
        $cacheKey = $this->cache->generateKey("inBattle", $killmail_id);
        if (
            $this->cache->exists($cacheKey) &&
            !empty(($cacheResult = $this->cache->get($cacheKey)))
        ) {
            return $this->json($cacheResult);
        }

        $killmailInBattle = $this->battleHelper->isKillInBattle($killmail_id);
        if ($killmailInBattle === null) {
            return $this->json(["error" => "Killmail not found"]);
        }

        if ($killmailInBattle === false) {
            $this->cache->set($cacheKey, ['inBattle' => false], 300);
            return $this->json(['inBattle' => false]);
        }

        $this->cache->set($cacheKey, ['inBattle' => true], 300);
        return $this->json(['inBattle' => true]);
    }

    #[RouteAttribute("/killmail[/]", ["POST"], "Get multiple killmails by ID")]
    public function killmails(): ResponseInterface
    {
        $postData = json_validate($this->getBody())
            ? json_decode($this->getBody(), true)
            : [];
        if (empty($postData)) {
            return $this->json(["error" => "No data provided"], 300, 400);
        }

        // Error if there are more than 1000 IDs
        if (count($postData) > 1000) {
            return $this->json(["error" => "Too many IDs provided"], 300, 400);
        }

        $killmails = $this->killmails
            ->find(
                ["killmail_id" => ['$in' => $postData]],
                ["projection" => ["_id" => 0]],
                300
            )
            ->map(function ($killmail) {
                return $this->cleanupTimestamps($killmail);
            })->map(function ($killmail) {
                $commentCount = $this->comments->count(['identifier' => 'kill:' . $killmail['killmail_id']]);
                $killmail['comment_count'] = $commentCount;
                return $killmail;
            });

        return $this->json($killmails->toArray(), 300);
    }

    #[RouteAttribute("/killmail/near/{system_id:[0-9]+}/{distanceInMeters:[0-9]+}/{x}/{y}/{z}[/{days:[0-9]+}]", ["GET"], "Get killmails near coordinates")]
    public function killmailsNearCoordinates(int $systemId, int $distanceInMeters, float $x, float $y, float $z, int $days = 1): ResponseInterface
    {
        $results = $this->killmails->aggregate([
            [
                '$match' => [
                    'system_id' => $systemId,
                    'x' => ['$gt' => $x - $distanceInMeters, '$lt' => $x + $distanceInMeters],
                    'y' => ['$gt' => $y - $distanceInMeters, '$lt' => $y + $distanceInMeters],
                    'z' => ['$gt' => $z - $distanceInMeters, '$lt' => $z + $distanceInMeters],
                    'kill_time' => ['$gte' => new \MongoDB\BSON\UTCDateTime((time() - ($days * 86400)) * 1000)]
                ]
            ],
            [
                '$project' => [
                    'killmail_id' => 1,
                    'distance' => [
                        '$sqrt' => [
                            '$add' => [
                                ['$pow' => [['$subtract' => ['$x', $x]], 2]],
                                ['$pow' => [['$subtract' => ['$y', $y]], 2]],
                                ['$pow' => [['$subtract' => ['$z', $z]], 2]],
                            ]
                        ]
                    ]
                ]
            ],
            [
                '$match' => [
                    'distance' => ['$lt' => $distanceInMeters]
                ]
            ],
            [
                '$sort' => ['distance' => -1]
            ],
            [
                '$limit' => 10
            ]
        ], ['allowDiskUse' => true, 'hint' => 'system_id_x_y_z']);

        return $this->json(iterator_to_array($results));
    }

    #[RouteAttribute("/killmail/near/{celestial_id:[0-9]+}/{distanceInMeters:[0-9]+}[/{days:[0-9]+}]", ["GET"], "Get killmails near a celestial")]
    public function killmailsNearCelestial(int $celestialId, int $distanceInMeters, int $days = 1): ResponseInterface
    {
        $celestial = $this->celestials->findOneOrNull(
            ['item_id' => $celestialId],
            ['projection' => ['_id' => 0]]
        );
        if ($celestial === null) {
            return $this->json(
                [
                    'error' => 'Celestial not found',
                ],
                300
            );
        }

        $results = $this->killmails->aggregate([
            [
                '$match' => [
                    'system_id' => $celestial['solar_system_id'],
                    'x' => ['$gt' => $celestial['x'] - $distanceInMeters, '$lt' => $celestial['x'] + $distanceInMeters],
                    'y' => ['$gt' => $celestial['y'] - $distanceInMeters, '$lt' => $celestial['y'] + $distanceInMeters],
                    'z' => ['$gt' => $celestial['z'] - $distanceInMeters, '$lt' => $celestial['z'] + $distanceInMeters],
                    'kill_time' => ['$gte' => new \MongoDB\BSON\UTCDateTime((time() - ($days * 86400)) * 1000)]
                ]
            ],
            [
                '$project' => [
                    'killmail_id' => 1,
                    'distance' => [
                        '$sqrt' => [
                            '$add' => [
                                ['$pow' => [['$subtract' => ['$x', $celestial['x']]], 2]],
                                ['$pow' => [['$subtract' => ['$y', $celestial['y']]], 2]],
                                ['$pow' => [['$subtract' => ['$z', $celestial['z']]], 2]],
                            ]
                        ]
                    ]
                ]
            ],
            [
                '$match' => [
                    'distance' => ['$lt' => $distanceInMeters]
                ]
            ],
            [
                '$sort' => ['distance' => -1]
            ],
            [
                '$limit' => 10
            ]
        ], ['allowDiskUse' => true, 'hint' => 'system_id_x_y_z']);

        return $this->json(iterator_to_array($results));
    }

    #[RouteAttribute("/killmail/history[/]", ["GET"], "Get all the dates available to fetch from the history API")]
    public function history(): ResponseInterface
    {
        // Fetch the oldest killmail
        $oldestKillmail = $this->killmails->findOne([], ['sort' => ['kill_time' => 1], 'projection' => ['kill_time' => 1], 'hint' => ['kill_time' => -1]], cacheTime: 5);

        // Fetch the latest killmail
        $newestKillmail = $this->killmails->findOne([], ['sort' => ['kill_time' => -1], 'projection' => ['kill_time' => 1], 'hint' => ['kill_time' => -1]], cacheTime: 5);

        if ($oldestKillmail && $newestKillmail) {
            $startDate = new \DateTime($oldestKillmail['kill_time']->toDateTime()->format('Y-m-d'));
            $endDate = new \DateTime($newestKillmail['kill_time']->toDateTime()->format('Y-m-d'));

            $dateInterval = new \DateInterval('P1D'); // 1 day interval
            $datePeriod = new \DatePeriod($startDate, $dateInterval, $endDate->modify('+1 day'));

            $uniqueDates = [];
            foreach ($datePeriod as $date) {
                $uniqueDates[] = $date->format('Ymd');
            }

            return $this->json($uniqueDates);
        }

        return $this->json([]); // Return empty array if no results
    }

    #[RouteAttribute("/killmail/history/{date:[0-9]+}[/]", ["GET"], "Get all the killmails for a specific date")]
    public function historyDate(string $date): ResponseInterface
    {
        // Validate the date format (Ymd)
        $dateRegex = '/^\d{4}\d{2}\d{2}$/';
        if (!preg_match($dateRegex, $date)) {
            return $this->json(['error' => 'Invalid date format. Expected Ymd.'], 400);
        }

        // Convert the date string to a DateTime object
        $startDate = \DateTime::createFromFormat('Ymd', $date);
        if (!$startDate) {
            return $this->json(['error' => 'Invalid date provided.'], 400);
        }

        // Define the start and end of the day
        $startOfDay = new \MongoDB\BSON\UTCDateTime($startDate->getTimestamp() * 1000); // MongoDB date in milliseconds
        $endOfDay = new \MongoDB\BSON\UTCDateTime(($startDate->modify('+1 day')->getTimestamp() - 1) * 1000);

        // Query to fetch all killmails for the specific day
        $killmails = $this->killmails->find([
            'kill_time' => [
                '$gte' => $startOfDay,
                '$lt' => $endOfDay
            ]
        ], [
            'projection' => [
                'killmail_id' => 1,
                'hash' => 1
            ]
        ], cacheTime: 5);

        // Prepare the result array
        $result = [];
        foreach ($killmails as $killmail) {
            $result[$killmail['killmail_id']] = $killmail['hash'];
        }

        return $this->json($result);
    }

    #[RouteAttribute("/killmail/latest[/]", ["GET"], "Get the latest killmail IDs in reverse order")]
    public function latest(): ResponseInterface
    {
        // Fetch the latest 1000 killmail IDs and their corresponding hashes
        $latestKillmails = $this->killmails->find([], [
            'sort' => ['last_modified' => -1],
            'projection' => ['killmail_id' => 1, 'hash' => 1],
            'limit' => 10000,
            'hint' => ['last_modified' => -1]
        ], cacheTime: 5);

        // Prepare the result as a key-value array where the key is killmail_id and the value is hash
        $result = [];
        foreach ($latestKillmails as $killmail) {
            $result[$killmail['killmail_id']] = $killmail['hash'];
        }

        return $this->json($result);
    }

}
