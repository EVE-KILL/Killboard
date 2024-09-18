<?php

namespace EK\Api\Attributes;

use Attribute;

#[Attribute]
class RouteAttribute
{
    private array $validTypes = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'];

    public function __construct(
        protected string $route,
        protected array $type = ['GET'],
        protected string $description = '',
        protected string $bodyExample = '',
        protected string $successReturn = '',
        protected string $errorReturn = '',
        protected bool $isPrivate = false,
    ) {
        $this->type = array_map(
            function ($t) {
                if (!in_array($t, $this->validTypes)) {
                    throw new \Exception('Error, type is not valid, needs to be one of: ' .
                        implode(', ', $this->validTypes) . ' was given: ' . $t);
                }
                return strtoupper($t);
            },
            $type
        );
    }

    public function getType(): array
    {
        return $this->type;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
