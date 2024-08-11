<?php

namespace EK\Helpers;

use EK\Config\Config;
use EK\Http\Fetcher;

class Ollama
{
    protected string $ollamaUrl = "http://127.0.0.1:11434/api/";
    protected ?string $allowedToken = null;
    protected string $model = 'llama3.1';

    public function __construct(
        protected Config $config,
        protected Fetcher $fetcher
    ) {
        $this->ollamaUrl = $this->config->get("ollama/url");
        $this->allowedToken = $this->config->get("ollama/token");
        $this->model = $this->config->get("ollama/model");
    }

    protected function sendRequest(string $url, array $data) {
        $response = $this->fetcher->fetch(
            $url,
            "POST",
            [],
            json_encode($data),
            [
                "Content-Type" => "application/json"
            ],
            cacheTime: 0
        );

        return json_decode($response["body"], true);
    }

    public function generateChat(array $messages, string $systemPrompt)
    {
        // Add the systemprompt to the top of the messages
        array_unshift($messages, [
            'role' => 'system',
            'content' => $systemPrompt,
        ]);

        return $this->sendRequest($this->ollamaUrl . "chat", [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
        ]);
    }

    public function generateText(string $text, string $systemPrompt)
    {
        $prompt = "system: " . $systemPrompt . "\nuser: " . $text . "\nsystem:";
        return $this->sendRequest($this->ollamaUrl . "generate", [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
        ]);
    }

    public function moderate(string $comment)
    {
        $systemPrompt = "You are a moderator for comments on an EVE-Online Killboard.\n
            Players will talk about things like killmails, kills, and will swear - you should allow this,\n
            but should also moderate the more crazy comments.\n
            Your output should simply be a score from 1 to 10. 1 being toxic and 10 being not-toxic.
            \n
            YOU CANNOT OUTPUT ANYTHING BUT A NUMBER, even if your cannot create content, then simply reply 1.
            You do NOT reply anything but a number, only a number between 1 and 10.\n
            The system reading this cannot understand text, it only understands numbers.\n
            \n\n
            Comment:\n
            ";
        return $this->generateText($comment, $systemPrompt);
    }
}
