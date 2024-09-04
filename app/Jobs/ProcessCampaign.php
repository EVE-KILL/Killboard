<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Helpers\Campaigns as CampaignsHelper;
use EK\Logger\Logger;
use EK\Models\Campaigns as CampaignsModel;
use EK\RabbitMQ\RabbitMQ;

class ProcessCampaign extends Jobs
{
    protected string $defaultQueue = "campaign";

    public function __construct(
        protected RabbitMQ $rabbitMQ,
        protected Logger $logger,
        protected CampaignsHelper $campaignsHelper,
        protected CampaignsModel $campaignsModel
    ) {
        parent::__construct($rabbitMQ, $logger);
    }

    public function handle(array $data): void
    {
        $campaignId = $data["campaign_id"];

        $campaignData = $this->campaignsHelper->generateCampaignStats($campaignId);

        $this->campaignsModel->setData($campaignData);
        $this->campaignsModel->save();
    }
}
