<?php

namespace App\Service\Client;

use App\Enum\EmbeddingEnum;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @template Result of array{
 *  payload: array{
 *      document: string,
 *  },
 * }
 */
class QdrantClient
{
    private HttpClientInterface $client;
    private string $baseUrl;

    public function __construct(HttpClientInterface $client, string $baseUrl)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
    }

    public function initCollection(EmbeddingEnum $type): static
    {
        $response = $this->client->request(
            'PUT',
            \sprintf('%s/collections/%s', $this->baseUrl, $type->value),
            [
                'json' => [
                    'vectors' => [
                        'size' => 384,
                        'distance' => 'Cosine',
                    ],
                ],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to create collection');
        }

        return $this;
    }

    public function removeCollection(EmbeddingEnum $type): static
    {
        $response = $this->client->request('DELETE', \sprintf('%s/collections/%s', $this->baseUrl, $type->value));

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to delete collection');
        }

        return $this;
    }

    public function upsert(EmbeddingEnum $type, array $embedding, string $document): float
    {
        if (384 !== count($embedding)) {
            throw new \InvalidArgumentException(\sprintf('Embedding must have 384 dimensions, got %d', count($embedding)));
        }

        $response = $this->client->request(
            'PUT',
            \sprintf('%s/collections/%s/points', $this->baseUrl, $type->value),
            [
                'json' => [
                    'points' => [[
                        'id' => Uuid::v7()->toString(),
                        'vector' => array_values($embedding),
                        'payload' => [
                            'document' => $document,
                        ],
                    ]],
                ],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to upsert point');
        }

        return $response->getInfo('total_time') ?? 0;
    }

    /**
     * @return array{
     *  time: float,
     *  results: string[],
     * }
     */
    public function search(EmbeddingEnum $type, array $embedding, int $limit): array
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/collections/%s/points/query', $this->baseUrl, $type->value),
            [
                'json' => [
                    'query' => $embedding,
                    'limit' => $limit,
                    'with_payload' => true,
                ],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to search: '.$response->getContent(false));
        }

        $points = $response->toArray()['result']['points'] ?? [];
        $results = [];
        foreach ($points as $point) {
            $results[] = $point['payload']['document'];
        }

        return [
            'time' => $response->getInfo('total_time') ?? 0,
            'results' => $results,
        ];
    }
}

