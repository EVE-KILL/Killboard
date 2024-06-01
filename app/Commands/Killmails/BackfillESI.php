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
        $totals = collect(json_decode(file_get_contents('https://data.everef.net/killmails/totals.json')));

        $totalCount = $totals->sum();
        $totalDays = $totals->count();
        $direction = $this->direction === 'forward' ? 'forward' : 'reverse';

        $this->out('Total killmails to import: ' . $totalCount);
        $this->out('Total days to import: ' . $totalDays);

        $this->out('Importing killmails from EVERef..');
        $progressBar = $this->progressBar($totalCount);

        $dates = $direction === 'forward' ? $totals->keys() : $totals->keys()->reverse();

        foreach($dates as $date) {
            $formattedDate = \DateTime::createFromFormat('Ymd', $date);
            $year = $formattedDate->format('Y');
            $month = $formattedDate->format('m');
            $day = $formattedDate->format('d');

            $url = "https://data.everef.net/killmails/{$year}/killmails-{$year}-{$month}-{$day}.tar.bz2";
            $this->processEveRefKillmails->enqueue(['url' => $url]);

            $progressBar->advance($totals[$date]);
        }

        $progressBar->finish();


    }
}
