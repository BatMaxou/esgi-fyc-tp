<?php

namespace App\Service\Client;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaClient
{
    private HttpClientInterface $client;
    private string $baseUrl;

    public function __construct(HttpClientInterface $client, string $baseUrl)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
    }

    public function ask(string $prompt): string
    {
        $response = $this->client->request('POST', sprintf('%s/api/generate', $this->baseUrl), [
            'json' => [
                'prompt' => $prompt,
                'stream' => false,
                'model' => 'llama3.2',
            ],
            'timeout' => 900,
        ]);

        $data = $response->toArray();

        return $data['response'] ?? '';
    }
}

