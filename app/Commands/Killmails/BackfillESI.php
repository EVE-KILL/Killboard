<?php

namespace EK\Commands\Killmails;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Jobs\processEveRefKillmails;

class BackfillESI extends ConsoleCommand
{
    public string $signature = 'import:killmails-everef
    { --direction=forward : The direction to import killmails from (forward or reverse) }
    ';
    public string $description = 'Import all ESI killmails known by EVERef';

    public function __construct(
        protected ClassLoader $autoloader,
        protected processEveRefKillmails $processEveRefKillmails,
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        // Get the total count of killmails to insert
        $totals = json_decode(file_get_contents('https://data.everef.net/killmails/totals.json'));

        $totalCount = collect($totals)->sum();
        $totalDays = collect($totals)->count();
        $earliestDate = \DateTime::createFromFormat('Ymd', collect($totals)->keys()->first());
        $latestDate = \DateTime::createFromFormat('Ymd', collect($totals)->keys()->last());

        $direction = $this->direction === 'forward' ? 'forward' : 'reverse';

        $this->out('Total killmails to import: ' . $totalCount);
        $this->out('Total days to import: ' . $totalDays);

        $this->out('Importing killmails from EVERef..');
        $progressBar = $this->progressBar($totalCount);

        if ($direction === 'forward') {
            $date = $earliestDate;
            do {
                $year = date('Y', $date->getTimestamp());
                $month = date('m', $date->getTimestamp());
                $day = date('d', $date->getTimestamp());

                $url = "https://data.everef.net/killmails/{$year}/killmails-{$year}-{$month}-{$day}.tar.bz2";
                $this->processEveRefKillmails->enqueue(['url' => $url]);
                $progressBar->advance();

                $date->modify('+1 day');
            } while($date->format('Ymd') <= $latestDate->format('Ymd'));
        } else {
            $date = $latestDate;
            do {
                $year = date('Y', $date->getTimestamp());
                $month = date('m', $date->getTimestamp());
                $day = date('d', $date->getTimestamp());

                $url = "https://data.everef.net/killmails/{$year}/killmails-{$year}-{$month}-{$day}.tar.bz2";
                $this->processEveRefKillmails->enqueue(['url' => $url]);
                $progressBar->advance();

                $date->modify('-1 day');
            } while($date->format('Ymd') >= $earliestDate->format('Ymd'));
        }

    }
}
