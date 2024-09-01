<?php

namespace EK\Helpers;

use EK\Fetchers\ESI;
use EK\Models\Alliances;
use EK\Models\Characters;
use EK\Models\Corporations;

class History
{
    public function __construct(
        protected ESI $esi,
        protected Characters $characters,
        protected Corporations $corporations,
        protected Alliances $alliances
    ) {}

    /**
     * Generates the full corporation history of a character,
     * including the alliance history for each corporation at the time.
     * This is a protected method to prevent external usage.
     */
    public function generateCorporationHistory(int $characterId)
    {
        $response = $this->esi->fetch(
            "/latest/characters/" . $characterId . "/corporationhistory",
            cacheTime: 60 * 60 * 24 * 7
        );

        $corpHistoryData = json_validate($response["body"])
            ? json_decode($response["body"], true)
            : [];
        $corpHistoryData = array_reverse($corpHistoryData);

        $corporationIds = array_column($corpHistoryData, "corporation_id");

        $corporationsData = $this->corporations->find(
            ["corporation_id" => ['$in' => $corporationIds]],
            [
                "projection" => [
                    "_id" => 0,
                    "corporation_id" => 1,
                    "name" => 1,
                ],
            ],
            300
        )->toArray();

        $corporationsDataAssoc = [];
        foreach ($corporationsData as $corporationData) {
            $corporationsDataAssoc[$corporationData["corporation_id"]] = $corporationData;
        }

        $corporationHistory = [];
        for ($i = 0; $i < count($corpHistoryData); $i++) {
            if (!isset($corpHistoryData[$i]["corporation_id"])) {
                continue;
            }

            $history = $corpHistoryData[$i];
            $corpData = $corporationsDataAssoc[$history["corporation_id"]] ?? null;

            if ($corpData === null) {
                $corpData = $this->corporations->findOne(['corporation_id' => $history["corporation_id"]])->toArray();
            }

            $allianceHistory = $this->getAllianceForPeriod($history["corporation_id"], $history["start_date"]);

            $joinDate = new \DateTime($history["start_date"]);
            $leaveDate = isset($corpHistoryData[$i + 1]) ? new \DateTime($corpHistoryData[$i + 1]["start_date"]) : null;

            $data = [
                "corporation_id" => $history["corporation_id"],
                "name" => $corpData['name'],
                "join_date" => $joinDate->format("Y-m-d H:i:s"),
            ];

            if ($leaveDate) {
                $data["leave_date"] = $leaveDate->format("Y-m-d H:i:s");
            }

            if ($allianceHistory) {
                $data["alliance"] = $allianceHistory;
            }

            $corporationHistory[] = $data;
        }

        return array_reverse($corporationHistory);
    }

    /**
     * Retrieves the full alliance history for a given corporation.
     */
    public function getFullAllianceHistory(int $corporationId)
    {
        $response = $this->esi->fetch(
            "/latest/corporations/" . $corporationId . "/alliancehistory/",
            cacheTime: 60 * 60 * 24 * 7
        );

        $allianceHistoryData = json_validate($response["body"])
            ? json_decode($response["body"], true)
            : [];
        $allianceHistoryData = array_reverse($allianceHistoryData);

        $alliances = [];
        for ($i = 0; $i < count($allianceHistoryData); $i++) {
            $history = $allianceHistoryData[$i];

            // Skip entries without an alliance_id, indicating the corporation wasn't in an alliance
            if (!isset($history["alliance_id"])) {
                continue;
            }

            $allianceData = $this->alliances->findOne(['alliance_id' => $history["alliance_id"]], [
                "projection" => [
                    "_id" => 0,
                    "alliance_id" => 1,
                    "name" => 1,
                ]
            ])->toArray();

            if ($allianceData === null) {
                $allianceData = [
                    "alliance_id" => $history["alliance_id"],
                    "name" => "Unknown Alliance"
                ];
            }

            $joinDate = new \DateTime($history["start_date"]);
            $leaveDate = isset($allianceHistoryData[$i + 1]) ? new \DateTime($allianceHistoryData[$i + 1]["start_date"]) : null;

            $allianceEntry = [
                "alliance_id" => $allianceData["alliance_id"],
                "name" => $allianceData["name"],
                "join_date" => $joinDate->format("Y-m-d H:i:s"),
            ];

            if ($leaveDate) {
                $allianceEntry["leave_date"] = $leaveDate->format("Y-m-d H:i:s");
            }

            $alliances[] = $allianceEntry;
        }

        return array_reverse($alliances);
    }

    /**
     * Retrieves the alliance that a corporation was part of during a specific period.
     */
    protected function getAllianceForPeriod(int $corporationId, string $startDate)
    {
        $response = $this->esi->fetch(
            "/latest/corporations/" . $corporationId . "/alliancehistory/",
            cacheTime: 60 * 60 * 24 * 7
        );

        $allianceHistoryData = json_validate($response["body"])
            ? json_decode($response["body"], true)
            : [];

        $startDateObj = new \DateTime($startDate);
        foreach ($allianceHistoryData as $allianceHistory) {
            // Skip entries without an alliance_id, indicating the corporation wasn't in an alliance
            if (!isset($allianceHistory["alliance_id"])) {
                continue;
            }

            $allianceStartDate = new \DateTime($allianceHistory["start_date"]);
            $allianceEndDate = isset($allianceHistory["end_date"]) ? new \DateTime($allianceHistory["end_date"]) : null;

            if ($startDateObj >= $allianceStartDate && (!$allianceEndDate || $startDateObj < $allianceEndDate)) {
                $allianceData = $this->alliances->findOne(['alliance_id' => $allianceHistory["alliance_id"]], [
                    "projection" => [
                        "_id" => 0,
                        "alliance_id" => 1,
                        "name" => 1,
                    ]
                ])->toArray();

                if ($allianceData) {
                    return [
                        "alliance_id" => $allianceData["alliance_id"],
                        "name" => $allianceData["name"],
                    ];
                }
            }
        }

        return null;
    }
}
