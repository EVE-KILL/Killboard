<?php

namespace EK\Commands\Killmails;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Jobs\processEveRefKillmails;

class BackfillESI extends ConsoleCommand
{
    public string $signature = 'import:killmails-everef';
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

        // Get the first date in the totals list
        $earliestTime = collect($totals)->keys()->first();

        // Create a DateTime object from the earliest time
        $date = \DateTime::createFromFormat('Ymd', $earliestTime);

        $this->out("Total killmails: {$totalCount}");
        $this->out("Earliest time: {$earliestTime}");

        // Create a progress bar
        $progress = $this->progressBar($totalDays);

        do {
            $year = $date->format('Y');
            $month = $date->format('m');
            $day = $date->format('d');

            $url = "https://data.everef.net/killmails/{$year}/killmails-{$year}-{$month}-{$day}.tar.bz2";

            // Send the job to the queue
            $this->processEveRefKillmails->enqueue(['url' => $url]);
            $progress->advance();

            // Increment the date
            $date->modify('+1 day');

        } while ($date->format('Y') <= date('Y')); // Modify this condition as needed

        $progress->finish();
    }
}
