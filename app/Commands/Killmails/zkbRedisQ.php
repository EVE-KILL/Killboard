<?php

namespace EK\Commands\Killmails;

use Composer\Autoload\ClassLoader;
use EK\Api\Abstracts\ConsoleCommand;
use EK\Jobs\ProcessKillmail;
use EK\Models\Killmails;
use GuzzleHttp\Client;

class zkbRedisQ extends ConsoleCommand
{
    public string $signature = 'redisq';
    public string $description = 'Use the zKillboard RedisQ to fetch killmails';

    public function __construct(
        protected ClassLoader $autoloader,
        protected Killmails $killmails,
        protected ProcessKillmail $processKillmail
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        $queueUrl = 'https://redisq.zkillboard.com/listen.php?queueID=evekill';
        $this->out('Fetching from: ' . $queueUrl);

        // The way redisq works, is that it blocks for upwards of 10 seconds
        // If a killmail exists, it returns the json
        // If a killmail doesn't exist, it returns json with "package": null
        // All we gotta do is spin around and call it as quickly as possible after a killmail is posted
        // And then insert it into the killmail db
        $run = true;
        do {
            try {
                $client = new Client();

                // Set user agent to avoid 403
                $response = $client->request('GET', $queueUrl, [
                    'headers' => [
                        'User-Agent' => 'EVE-KILL'
                    ]
                ]);

                $statusCode = $response->getStatusCode();
                $killmail = $response->getBody()->getContents();
                $kill = json_validate($killmail) ? json_decode($killmail, true) : null;

                if ($kill !== null && $kill['package'] !== null) {
                    // Check the killmail doesn't already exist
                    if ($this->killmails->findOne(['killmail_id' => $kill['package']['killID']])->isNotEmpty()) {
                        $this->out('Killmail already exists: ' . $kill['package']['killID']);
                        continue;
                    }
                    $this->out('Inserting killmail: ' . $kill['package']['killID']);
                    $this->killmails->collection->insertOne($this->formatKillmail($kill));

                    // Send to the queue
                    $this->processKillmail->enqueue([
                        'killmail_id' => $kill['package']['killID'],
                        'hash' => $kill['package']['zkb']['hash'],
                        'priority' => 10
                    ], priority: 10);
                }
            } catch (\Exception $e) {
                $this->out('Error: ' . $e->getMessage());
                $run = false;
            }
        } while($run === true);
    }

    private function formatKillmail(array $killmail): array
    {
        return [
            'killmail_id' => $killmail['package']['killID'],
            'hash' => $killmail['package']['zkb']['hash']
        ];
    }
}
