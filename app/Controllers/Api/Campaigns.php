<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Helpers\Campaigns as CampaignHelper;
use EK\Jobs\ProcessCampaign;
use EK\Models\Campaigns as CampaignsModel;
use EK\Models\Users;
use Psr\Http\Message\ResponseInterface;
use Sirius\Validation\Validator;

class Campaigns extends Controller
{
    public function __construct(
        protected CampaignsModel $campaigns,
        protected CampaignHelper $campaignHelper,
        protected ProcessCampaign $processCampaign,
        protected Users $users
    ) {
        parent::__construct();
    }

    #[RouteAttribute("/campaigns[/]", ["GET"], "Get all campaigns")]
    public function all(): ResponseInterface
    {
        $campaigns = [];
        return $this->json($campaigns, 300);
    }

    #[RouteAttribute("/campaigns[/]", ["POST"], "Create a campaign")]
    public function create(): ResponseInterface
    {
        $postData = json_validate($this->getBody())
            ? json_decode($this->getBody(), true)
            : [];
        if (empty($postData)) {
            return $this->json(["error" => "No data provided"], 300);
        }

        // Error if there are more than 1000 IDs
        if (count($postData) > 1000) {
            return $this->json(["error" => "Too many IDs provided"], 300);
        }

        $validator = new Validator();
        $validator->add('name', 'required');
        $validator->add('description', 'required');

        if (!$validator->validate($postData)) {
            return $this->json($validator->getMessages(), 300);
        }

        // Ensure there is at least one entity
        $entities = $postData['entities'] ?? [];
        if (empty($entities)) {
            return $this->json(["error" => "No entities provided"], 300);
        }

        // Check the identifier is valid
        $identifier = $postData['user']['identifier'] ?? '';
        if ($this->users->validateIdentifier($identifier) === false) {
            return $this->json(["error" => "Invalid identifier"], 300);
        }

        // Everything checks out, store the campaign in the database
        $postData['campaign_id'] = hash('sha256', json_encode($postData));

        $this->campaigns->setData($postData);
        $this->campaigns->save();

        // Queue the campaign for processing
        $this->processCampaign->enqueue(['campaign_id' => $postData['campaign_id']]);

        return $this->json(['success' => true], 0);
    }
}
