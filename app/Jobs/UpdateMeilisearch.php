<?php

namespace EK\Jobs;

use EK\Api\Abstracts\Jobs;
use EK\Logger\Logger;
use EK\Meilisearch\Meilisearch;
use EK\RabbitMQ\RabbitMQ;

class UpdateMeilisearch extends Jobs
{
    protected string $defaultQueue = 'meilisearch';
    public bool $requeue = false;

    public function __construct(
        protected Meilisearch $meilisearch,
        protected RabbitMQ $rabbitMQ,
        protected Logger $logger,
    ) {
        parent::__construct($rabbitMQ, $logger);
    }

    public function handle(array $data): void
    {
        $id = $data['id'];
        $name = $data['name'];
        $type = $data['type'];

        $this->meilisearch->addDocuments([
            'id' => $id,
            'name' => $name,
            'type' => $type,
        ]);
    }
}
