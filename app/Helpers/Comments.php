<?php

namespace EK\Helpers;

use EK\Config\Config;
use EK\Models\Comments as CommentsModel;
use EK\Webhooks\Webhooks;
use GuzzleHttp\Client;

class Comments
{
    protected Client $client;
    public function __construct(
        protected CommentsModel $comments,
        protected Config $config,
        protected Webhooks $webhooks
    ) {
        $this->client = new Client();
    }

    public function aiModeration(string $comment): array
    {
        // Use OpenAI's Moderation API to check if the comment is safe
        $response = $this->client->post('https://api.openai.com/v1/moderations', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->config->get('openai/comments')
            ],
            'body' => json_encode([
                'input' => $comment
            ])
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        // Return the first result
        return $data['results'][0] ?? [];
    }

    public function isFlagged(array $moderation): bool
    {
        return $moderation['categories']['self-harm'] ||
            $moderation['categories']['sexual/minors'] ||
            $moderation['categories']['self-harm/intent'] ||
            $moderation['categories']['self-harm/instructions'];
    }

    public function emitToDiscord(array $comment): void
    {
        $commentLink = "https://eve-kill.com/" . implode('/', explode(':', $comment['identifier']));
        $this->webhooks->sendToComments(
            "New comment by {$comment['character']['character_name']} on {$commentLink} ```{$comment['comment']}```"
        );
    }
}
