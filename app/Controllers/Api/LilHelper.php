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

    #[RouteAttribute("/lilhelper/dscan/{id}[/]", ["GET"], "Get DScan results")]
    public function dScanGet(string $id): ResponseInterface
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

    #[RouteAttribute("/lilhelper/dscan[/]", ["POST"], "Post DScan results")]
    public function dScan(): ResponseInterface
    {
        $postData = json_validate($this->getBody()) ? json_decode($this->getBody(), true) : [];
        if (empty($postData)) {
            return $this->json(['error' => 'No data provided']);
        }

        $returnData = [];

        // Count the amount of times a ship appears in the dscan
        foreach($postData as $ship) {
            if (empty($returnData['ships'][$ship])) {
                $returnData['ships'][$ship] = 1;
            } else {
                $returnData['ships'][$ship]++;
            }
        }

        $returnData['hash'] = hash('sha256', json_encode($returnData));

        $this->lilHelper->setData($returnData);
        $this->lilHelper->save();

        return $this->json($returnData);
    }

    #[RouteAttribute("/lilhelper/localscan/{id}[/]", ["GET"], "Get LocalScan results")]
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

    #[RouteAttribute("/lilhelper/localscan[/]", ["POST"], "Post LocalScan results")]
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
