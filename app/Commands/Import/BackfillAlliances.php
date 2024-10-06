<?php

namespace EK\Commands\Import;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Fetchers\ESI;
use EK\Jobs\UpdateAlliance;

class BackfillAlliances extends ConsoleCommand
{
    public string $signature = 'import:alliances';
    public string $description = 'Import all alliances';

    public function __construct(
        protected ClassLoader $autoloader,
        protected UpdateAlliance $updateAlliance,
        protected ESI $esi
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $request = $this->esi->fetch('/latest/alliances');
        $alliances = json_validate($request['body']) ? json_decode($request['body'], true) : [];

        $progress = $this->progressBar(count($alliances));

        foreach ($alliances as $allianceId) {
            $this->updateAlliance->enqueue(['alliance_id' => $allianceId, 'update_history' => true]);
            $progress->advance();
        }

        $progress->finish();
    }
}
