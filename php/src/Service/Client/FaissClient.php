<?php

namespace App\Service\Client;

use App\Enum\EmbeddingEnum;
use Symfony\Component\Uid\Uuid;
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

    public function reset(): static
    {
        $response = $this->client->request(
            'DELETE',
            \sprintf('%s/delete', $this->baseUrl),
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to search: '.$response->getContent(false));
        }

        return $this;
    }

    public function initIndex(): static
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/init', $this->baseUrl),
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to search: '.$response->getContent(false));
        }

        return $this;
    }

    public function insert(array $embedding, string $document): static
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/add', $this->baseUrl),
            [
                'json' => [
                    'ids' => [crc32(Uuid::v7()->toString())],
                    'vectors' => [$embedding],
                    'documents' => [$document],
                ],

            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to insert document');
        }

        return $this;
    }

    public function search(array $embedding): static
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/search', $this->baseUrl),
            [
                'json' => [
                    'query_vectors' => [$embedding],
                ],

            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to search document');
        }

        return $this;
    }
}

