<?php

namespace EK\Webhooks;

use EK\Config\Config;

class Webhooks
{
    public function __construct(
        protected Config $config
    ) {
    }

    public function sendToErrors(string $message): void
    {
        $this->send($this->config->get('webhooks/errors'), $message);
    }

    public function sendToEsiErrors(string $message): void
    {
        $this->send($this->config->get('webhooks/esi-errors'), $message);
    }

    public function sendToComments(string $message): void
    {
        $this->send($this->config->get('webhooks/comments'), $message);
    }

    private function send(string $url, string $message): void
    {
        try {
            $data = [
                'content' => $message
            ];

            $options = [
                'http' => [
                    'header'  => "Content-type: application/json\r\n",
                    'method'  => 'POST',
                    'content' => json_encode($data)
                ]
            ];

            $context  = stream_context_create($options);
            file_get_contents($url, false, $context);
        } catch (\Exception $e) {
            // Do nothing
        }
    }
}
