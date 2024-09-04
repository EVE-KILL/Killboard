<?php

namespace EK\Cronjobs;

use EK\Api\Abstracts\Cronjob;
use EK\Jobs\ProcessCampaign;
use EK\Logger\StdOutLogger;
use EK\Models\Campaigns as CampaignsModel;

class Campaigns extends Cronjob
{
    protected string $cronTime = '*/15 * * * *';

    public function __construct(
        protected StdOutLogger $logger,
        protected CampaignsModel $campaignsModel,
        protected ProcessCampaign $processCampaign
    ) {
        parent::__construct($logger);
    }

    public function handle(): void
    {
        // Find campaigns that have a last_modified older than 1 hour
        $this->campaignsModel->find([
            'last_modified' => ['$lt' => new \MongoDB\BSON\UTCDateTime(strtotime('-1 hour') * 1000)]
        ])->each(function ($campaign) {
            $this->processCampaign->enqueue(['campaign_id' => $campaign['campaign_id']]);
        });
    }
}
