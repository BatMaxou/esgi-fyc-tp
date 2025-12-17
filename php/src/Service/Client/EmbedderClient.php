<?php

namespace App\Service\Client;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class EmbedderClient
{
    private HttpClientInterface $client;
    private string $baseUrl;

    public function __construct(HttpClientInterface $client, string $baseUrl)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
    }

    public function embed(string $text): array
    {
        $response = $this->client->request('POST', $this->baseUrl, [
            'json' => ['text' => $text],
        ]);

        return $response->toArray();
    }

    public function getEmbededDocument(): array
    {
        $response = $this->client->request('POST', \sprintf('%s/document', $this->baseUrl));

        return $response->toArray();
    }
}

