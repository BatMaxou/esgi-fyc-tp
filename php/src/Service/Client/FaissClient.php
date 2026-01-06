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

    public function insert(array $embedding, string $document): float
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

        return $response->getInfo('total_time') ?? 0;
    }

    /**
     * @return array{
     *  time: float,
     *  results: string[],
     * }
     */
    public function search(array $embedding, int $limit): array
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/search', $this->baseUrl),
            [
                'json' => [
                    'query_vectors' => [$embedding],
                    'limit' => $limit,
                ],

            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to search document');
        }

        $faissResults = $response->toArray()['results'][0] ?? [];
        $results = [];
        foreach ($faissResults as $faissResult) {
            $results[] = $faissResult['document'];
        }

        return [
            'time' => $response->getInfo('total_time') ?? 0,
            'results' => $results,
        ];
    }
}

