<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Config\Config;
use EK\Helpers\Ollama as HelpersOllama;
use Psr\Http\Message\ResponseInterface;

class Ollama extends Controller
{
    public function __construct(
        protected HelpersOllama $ollamaHelper,
        protected Config $config
    ) {
        parent::__construct();
    }

    #[RouteAttribute('/ollama/chat', ['POST'], 'Generate a chat')]
    public function chat(): ResponseInterface
    {
        $postData = json_validate($this->getBody()) ? json_decode($this->getBody(), true) : [];

        if (empty($postData)) {
            return $this->json(['error' => 'No data provided'], 300, 400);
        }

        $validator = $this->newValidator();
        $validator->add([
            'messages' => 'required',
            'systemPrompt' => 'required',
            'token' => 'required',
        ]);

        if (!$this->validator->validate($postData)) {
            return $this->json(['error' => 'Invalid data'], 300, 400);
        }

        // Validate the contents of messages
        foreach($postData['messages'] as $message) {
            $validator = $this->newValidator();
            $validator->add([
                'role' => 'required',
                'content' => 'required',
            ]);
            if (!$this->validator->validate($message)) {
                return $this->json(['error' => 'Invalid data ddd'], 300, 400);
            }
        }

        // Validate the token
        $token = $this->config->get('ollama/token');
        if ($postData['token'] !== $token) {
            return $this->json(['error' => 'Invalid token'], 300, 400);
        }

        // Everything seems to check out, lets get the result from Ollama
        $response = $this->ollamaHelper->generateChat($postData['messages'], $postData['systemPrompt']);
        return $this->json($response);

    }
}
