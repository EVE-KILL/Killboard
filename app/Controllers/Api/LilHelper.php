<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Models\Characters;
use EK\Models\LilHelper as LilHelperModel;
use Psr\Http\Message\ResponseInterface;

class LilHelper extends Controller
{
    public function __construct(
        protected Characters $characters,
        protected LilHelperModel $lilHelper
    ) {
        parent::__construct();
    }

    #[RouteAttribute("/lilhelper/dscan[/]", ["POST"], "")]
    public function dScan(): ResponseInterface
    {
        return $this->json([]);
    }

    #[RouteAttribute("/lilhelper/localscan/{id}[/]", ["GET"], "")]
    public function localScanGet(string $id): ResponseInterface
    {
        $data = $this->lilHelper->findOne(['hash' => $id], ['projection' => [
            '_id' => 0,
            'hash' => 0,
            'last_modified' => 0
        ]]);
        if (empty($data)) {
            return $this->json(['error' => 'No data found']);
        }

        return $this->json($data);
    }

    #[RouteAttribute("/lilhelper/localscan[/]", ["POST"], "")]
    public function localScan(): ResponseInterface
    {
        $postData = json_validate($this->getBody()) ? json_decode($this->getBody(), true) : [];
        if (empty($postData)) {
            return $this->json(['error' => 'No data provided']);
        }

        $returnData = [];
        foreach($postData as $characterName) {
            $character = $this->characters->findOne(['name' => $characterName]);
            if ($character['alliance_id'] > 0) {
                $returnData['alliances'][$character['alliance_id']]['name'] = $character['alliance_name'];
                $returnData['alliances'][$character['alliance_id']]['corporations'][] = $character['corporation_name'];
            } else {
                $returnData['corporations'][$character['corporation_id']] = $character['corporation_name'];
            }

        }

        $returnData['hash'] = hash('sha256', json_encode($returnData));

        $this->lilHelper->setData($returnData);
        $this->lilHelper->save();

        return $this->json($returnData);

    }

}
