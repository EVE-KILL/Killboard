<?php

namespace EK\Commands\Battles;

use EK\Api\Abstracts\ConsoleCommand;
use EK\Helpers\Battle;
use EK\Models\Battles;
use EK\Models\Corporations;
use EK\Models\Killmails;
use EK\Models\SolarSystems;
use Illuminate\Support\Collection;
use MongoDB\BSON\UTCDateTime;

class BackfillBattles extends ConsoleCommand
{
    protected string $signature = 'battles:backfill {--starttime=} {--endtime=}';
    protected string $description = 'Backfill battles';

    public function __construct(
        protected Killmails $killmails,
        protected Battles $battles,
        protected SolarSystems $solarSystems,
        protected Corporations $corporations,
        protected Battle $battleHelper,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    final public function handle(): void
    {
        $times = $this->getStartAndEndTime();
        $startTime = $times['unixTime']['startTime'];
        $endTime = $times['unixTime']['endTime'];
        $formattedStartTime = $times['formattedTime']['startTime'];
        $formattedEndTime = $times['formattedTime']['endTime'];

        $this->out("Backfilling battles from {$formattedStartTime} to {$formattedEndTime}");

        $fromTime = $startTime - 7200; // - 3600;
        $toTime = $startTime; // + 3600;
        do {
            //$this->out('Looking for battles between ' . date('Y-m-d H:i:s', $fromTime) . ' and ' . date('Y-m-d H:i:s', $toTime));
            $pipeline = [
                ['$match' => [
                    'kill_time' => ['$gte' => new UTCDateTime($fromTime * 1000), '$lte' => new UTCDateTime($toTime * 1000)]
                ]],
                ['$group' => [
                    '_id' => '$system_id',
                    'count' => ['$sum' => 1]
                ]],
                ['$match' => [
                    'count' => ['$gt' => 25]
                ]],
                ['$sort' => ['count' => -1]]
            ];

            $potentialBattles = $this->killmails->aggregate($pipeline, ['hint' => 'kill_time']);

            // This is where we start looking for the battle in 5 minute segments
            if ($potentialBattles->count() > 0) {
                foreach($potentialBattles as $potentialBattle) {
                    $systemId = $potentialBattle['_id'];
                    //$this->out("Potential battle in system {$systemId}");

                    $extensibleToTime = $toTime;
                    $segmentStart = $fromTime;
                    $segmentEnd = $fromTime + 300;
                    $foundStart = false;
                    $foundEnd = false;
                    $battleStartTime = 0;
                    $battleEndTime = 0;
                    $failCounter = 0;
                    $killCountToConsider = 25;

                    do {
                        $killCount = $this->killmails->count([
                            'kill_time' => ['$gte' => new UTCDateTime($segmentStart * 1000), '$lte' => new UTCDateTime($segmentEnd * 1000)],
                            'system_id' => $systemId
                        ]);

                        //$this->out("Segment between " . date('Y-m-d H:i:s', $segmentStart) . " and " . date('Y-m-d H:i:s', $segmentEnd) . " has {$killCount} kills");

                        if ($killCount >= $killCountToConsider) {
                            if (!$foundStart) {
                                $foundStart = true;
                                $battleStartTime = $segmentStart;
                            }
                            $failCounter = 0;
                        } else {
                            if ($failCounter >= 5) {
                                $foundEnd = true;
                                $battleEndTime = $segmentStart;
                            }
                            $failCounter++;
                        }

                        // We can _ONLY_ extend $toTime by 1h if we hit >5 kills in the last 5 minute segment
                        if ($segmentEnd >= $extensibleToTime && $killCount >= $killCountToConsider) {
                            $this->out("Extending toTime by 30m because we hit >5 kills in the last 5 minute segment");
                            $extensibleToTime += 1600;
                        }

                        $segmentStart += 300;
                        $segmentEnd += 300;
                    } while ($segmentEnd < $extensibleToTime);

                    if ($foundStart && $foundEnd) {
                        $this->out('Found a battle in system ' . $systemId . ' between ' . date('Y-m-d H:i:s', $battleStartTime) . ' and ' . date('Y-m-d H:i:s', $battleEndTime));
                        if ($battleEndTime < $battleStartTime) {
                            $this->out('Battle end time is before start time, skipping');
                            continue;
                        }

                        // Get the battle data from the battle helper
                        $battle = $this->battleHelper->processBattle($systemId, $battleStartTime, $battleEndTime);

                        // Insert the battle into the database
                        $battleId = md5(json_encode($battle, JSON_THROW_ON_ERROR, 512));
                        $battle['battle_id'] = $battleId;

                        // Save to the db
                        $this->battles->setData($battle);
                        $this->battles->save();
                    }
                }
            }

            $fromTime += 7200;
            $toTime += 7200;
        } while($fromTime < $endTime);
    }



    private function getStartAndEndTime(): array
    {
        // Find the earliest killmail and get it's kill_time
        $earliestKillmail = $this->killmails->findOne([], ['hint' => 'kill_time', 'sort' => ['kill_time' => 1]]);
        /** @var UTCDateTime $earliestTime */
        $earliestTime = $earliestKillmail->get('kill_time');
        $calculatedStartTime = $earliestTime->toDateTime()->getTimestamp();
        $calculatedEndTime = time();

        // Get the command line options
        $inputStartTime = $this->starttime;
        $inputEndTime = $this->endtime;

        // If the options are not set, use the calculated times
        $startTime = $inputStartTime ? strtotime($inputStartTime) : $calculatedStartTime;
        $endTime = $inputEndTime ? strtotime($inputEndTime) : $calculatedEndTime;

        // Format the timestamps to 'Y-m-d H:i:s'
        $formattedStartTime = date('Y-m-d H:i:s', $startTime);
        $formattedEndTime = date('Y-m-d H:i:s', $endTime);

        return [
            'unixTime' => [
                'startTime' => $startTime,
                'endTime' => $endTime
            ],
            'formattedTime' => [
                'startTime' => $formattedStartTime,
                'endTime' => $formattedEndTime
            ]
        ];
    }
}
