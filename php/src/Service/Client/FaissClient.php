<?php

namespace App\Service\Client;

use App\Enum\EmbeddingEnum;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class FaissClient
{
    private HttpClientInterface $client;
    private string $baseUrl;

    public function __construct(HttpClientInterface $client, string $baseUrl)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
    }

    public function initIndex(): array
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/init', $this->baseUrl),
        );

        dd($response->toArray());

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to search: '.$response->getContent(false));
        }

        return $response->toArray()['result']['points'] ?? [];
    }

    public function status(): array
    {
        $response = $this->client->request(
            'GET',
            \sprintf('%s/status', $this->baseUrl),
        );

        dd($response->toArray());

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to search: '.$response->getContent(false));
        }

        return $response->toArray()['result']['points'] ?? [];
    }
}

