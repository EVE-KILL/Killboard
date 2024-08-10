<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Helpers\Query as HelpersQuery;
use Psr\Http\Message\ResponseInterface;

class Query extends Controller
{
    public function __construct(
        protected HelpersQuery $queryHelper,
    ) {
    }

    protected function validateParams(string $params): array
    {
        // Explode on /, remove trailing /
        $params = explode("/", rtrim($params, '/'));
        $validParams = array_merge($this->queryHelper->validQueryParams, $this->queryHelper->validSortParams);

        $count = 0;
        $tempParams = [];

        if (count($params) >= 2) {
            foreach ($params as $param) {
                if (empty($param)) {
                    continue;
                }

                // Get key-value pairs
                if ($count % 2 == 0) {
                    $key = $param;
                    $value = $params[$count + 1] ?? null;

                    // Check if the parameter is valid
                    if (isset($validParams[$key])) {
                        $expectedType = $validParams[$key];

                        // Typecast based on the expected type
                        $castedValue = $this->castValue($value, $expectedType);

                        if ($castedValue !== null) {
                            $tempParams[$key] = $castedValue;
                        } else {
                            throw new \InvalidArgumentException("Invalid type for parameter '$key'. Expected $expectedType.");
                        }
                    } else {
                        throw new \InvalidArgumentException("Invalid parameter '$key' provided.");
                    }
                }

                $count++;
            }
        }

        // Apply additional validation for specific parameters
        $tempParams['page'] = $tempParams['page'] ?? 1;
        $tempParams['limit'] = $tempParams['limit'] ?? 1000;
        $tempParams['offset'] = $tempParams['offset'] ?? 0;
        $tempParams['order'] = $tempParams['order'] ?? 'DESC';

        // Adjust offset based on page and limit
        if ($tempParams['page'] > 1) {
            $tempParams['offset'] = $tempParams['limit'] * ($tempParams['page'] - 1);
        }

        // Enforce limits on 'limit'
        if ($tempParams['limit'] > 1000) {
            $tempParams['limit'] = 1000;
        } elseif ($tempParams['limit'] < 1) {
            $tempParams['limit'] = 1;
        }

        // Validate the 'order' parameter
        $validOrder = ['ASC', 'DESC'];
        if (!in_array(strtoupper($tempParams['order']), $validOrder, true)) {
            $tempParams['order'] = 'DESC';
        }

        return $tempParams;
    }

    private function castValue($value, string $type): mixed
    {
        switch ($type) {
            case 'int':
                return is_numeric($value) ? (int)$value : null;
            case 'float':
                return is_numeric($value) ? (float)$value : null;
            case 'bool':
                $lowerValue = strtolower($value);
                if (in_array($lowerValue, ['true', '1'], true)) {
                    return true;
                } elseif (in_array($lowerValue, ['false', '0'], true)) {
                    return false;
                }
                return null;
            case 'datetime':
                $datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
                return $datetime ? $datetime : null;
            case 'string':
                return is_string($value) ? (string)$value : null;
            default:
                return null;
        }
    }

    // System
    #[RouteAttribute("/query/system/{systemId:[0-9]+}[/{params:.*}]", ["GET"], "Query system for killmails")]
    public function querySystem(int $systemId, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->getBySystemId($systemId, $params);
        return $this->json($result);
    }

    // Region
    #[RouteAttribute("/query/region/{regionId:[0-9]+}[/{params:.*}]", ["GET"], "Query region for killmails")]
    public function queryRegion(int $regionId, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->getByRegionId($regionId, $params);
        return $this->json($result);
    }

    // Character
    #[RouteAttribute("/query/character/{characterId:[0-9]+}[/{params:.*}]", ["GET"], "Query character for killmails")]
    public function queryCharacter(int $characterId, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->getByCharacterId($characterId, $params);
        return $this->json($result);
    }

    // Corporation
    #[RouteAttribute("/query/corporation/{corporationId:[0-9]+}[/{params:.*}]", ["GET"], "Query corporation for killmails")]
    public function queryCorporation(int $corporationId, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->getByCorporationId($corporationId, $params);
        return $this->json($result);
    }

    // Alliance
    #[RouteAttribute("/query/alliance/{allianceId:[0-9]+}[/{params:.*}]", ["GET"], "Query alliance for killmails")]
    public function queryAlliance(int $allianceId, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->getByAllianceId($allianceId, $params);
        return $this->json($result);
    }

    // Faction
    #[RouteAttribute("/query/faction/{factionId:[0-9]+}[/{params:.*}]", ["GET"], "Query faction for killmails")]
    public function queryFaction(int $factionId, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->getByFactionId($factionId, $params);
        return $this->json($result);
    }

    // WeaponType
    #[RouteAttribute("/query/weapon/{weaponId:[0-9]+}[/{params:.*}]", ["GET"], "Query weapon for killmails")]
    public function queryWeapon(int $weaponId, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->getByWeaponTypeId($weaponId, $params);
        return $this->json($result);
    }

    // Ship
    #[RouteAttribute("/query/ship/{shipId:[0-9]+}[/{params:.*}]", ["GET"], "Query ship for killmails")]
    public function queryShip(int $shipId, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->getByShipId($shipId, $params);
        return $this->json($result);
    }

    // AfterDate
    #[RouteAttribute("/query/after/{unixTime:[0-9]+}[/{params:.*}]", ["GET"], "Query after date for killmails")]
    public function queryAfterDate(string $unixTime, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->getAfterDate($unixTime, $params);
        return $this->json($result);
    }

    // BeforeDate
    #[RouteAttribute("/query/before/{unixTime:[0-9]+}[/{params:.*}]", ["GET"], "Query before date for killmails")]
    public function queryBeforeDate(string $unixTime, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->getBeforeDate($unixTime, $params);
        return $this->json($result);
    }

    // BetweenDates
    #[RouteAttribute("/query/between/{unixTimeFrom:[0-9]+}/{unixTimeTill:[0-9]+}[/{params:.*}]", ["GET"], "Query between dates for killmails")]
    public function queryBetweenDates(string $unixTimeFrom, string $unixTimeTill, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->getBetweenDates($unixTimeFrom, $unixTimeTill, $params);
        return $this->json($result);
    }

    // TotalValueLess
    #[RouteAttribute("/query/totalValue/less/{value:[0-9]+}[/{params:.*}]", ["GET"], "Query total value less for killmails")]
    public function queryTotalValueLess(float $value, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->totalValueLess($value, $params);
        return $this->json($result);
    }

    // TotalValueMore
    #[RouteAttribute("/query/totalValue/more/{value:[0-9]+}[/{params:.*}]", ["GET"], "Query total value more for killmails")]
    public function queryTotalValueMore(float $value, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->totalValueMore($value, $params);
        return $this->json($result);
    }

    // TotalValueBetween
    #[RouteAttribute("/query/totalValue/between/{valueFrom:[0-9]+}/{valueTill:[0-9]+}[/{params:.*}]", ["GET"], "Query total value between for killmails")]
    public function queryTotalValueBetween(float $valueFrom, float $valueTill, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->totalValueBetween($valueFrom, $valueTill, $params);
        return $this->json($result);
    }

    // ShipValueLess
    #[RouteAttribute("/query/shipValue/less/{value:[0-9]+}[/{params:.*}]", ["GET"], "Query ship value less for killmails")]
    public function queryShipValueLess(float $value, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->shipValueLess($value, $params);
        return $this->json($result);
    }

    // ShipValueMore
    #[RouteAttribute("/query/shipValue/more/{value:[0-9]+}[/{params:.*}]", ["GET"], "Query ship value more for killmails")]
    public function queryShipValueMore(float $value, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->shipValueMore($value, $params);
        return $this->json($result);
    }

    // ShipValueBetween
    #[RouteAttribute("/query/shipValue/between/{value1:[0-9]+}/{value2:[0-9]+}[/{params:.*}]", ["GET"], "Query ship value between for killmails")]
    public function queryShipValueBetween(float $value1, float $value2, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->shipValueBetween($value1, $value2, $params);
        return $this->json($result);
    }

    // PointValueLess
    #[RouteAttribute("/query/pointValue/less/{value:[0-9]+}[/{params:.*}]", ["GET"], "Query point value less for killmails")]
    public function queryPointValueLess(float $value, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->pointValueLess($value, $params);
        return $this->json($result);
    }

    // PointValueMore
    #[RouteAttribute("/query/pointValue/more/{value:[0-9]+}[/{params:.*}]", ["GET"], "Query point value more for killmails")]
    public function queryPointValueMore(float $value, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->pointValueMore($value, $params);
        return $this->json($result);
    }

    // PointValueBetween
    #[RouteAttribute("/query/pointValue/between/{valueFrom:[0-9]+}/{valueTill:[0-9]+}[/{params:.*}]", ["GET"], "Query point value between for killmails")]
    public function queryPointValueBetween(float $valueFrom, float $valueTill, string $params): ResponseInterface
    {
        $params = $this->validateParams($params);
        $result = $this->queryHelper->pointValueBetween($valueFrom, $valueTill, $params);
        return $this->json($result);
    }
}
