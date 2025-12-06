<?php

namespace App\Service\Client;

use App\Enum\EmbeddingEnum;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class QdrantClient
{
    public const COLLECTION_MAPPING = [
        EmbeddingEnum::PDF->value => 'pdf',
    ];

    private HttpClientInterface $client;
    private string $baseUrl;

    public function __construct(HttpClientInterface $client, string $baseUrl)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
    }

    public function initCollection(EmbeddingEnum $type): void
    {
        $response = $this->client->request(
            'PUT',
            \sprintf('%s/collections/%s', $this->baseUrl, self::COLLECTION_MAPPING[$type->value]),
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
    }

    public function removeCollection(EmbeddingEnum $type): void
    {
        $response = $this->client->request('DELETE', \sprintf('%s/collections/%s', $this->baseUrl, self::COLLECTION_MAPPING[$type->value]));

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to delete collection');
        }
    }

    public function upsert(string $id, EmbeddingEnum $type, array $embedding, array $payload): ResponseInterface
    {
        if (384 !== count($embedding)) {
            throw new \InvalidArgumentException(\sprintf('Embedding must have 384 dimensions, got %d', count($embedding)));
        }

        $response = $this->client->request(
            'PUT',
            \sprintf('%s/collections/%s/points', $this->baseUrl, self::COLLECTION_MAPPING[$type->value]),
            [
                'json' => [
                    'points' => [[
                        'id' => $id,
                        'vector' => array_values($embedding),
                        'payload' => $payload,
                    ]],
                ],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to upsert point');
        }

        return $response;
    }

    public function delete(string $id, EmbeddingEnum $type): ResponseInterface
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/collections/%s/points/delete', $this->baseUrl, self::COLLECTION_MAPPING[$type->value]),
            [
                'json' => [
                    'points' => [$id],
                ],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to delete point');
        }

        return $response;
    }

    public function recommend(array $embeddings, int $top = 20): array
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/collections/%s/points/query', $this->baseUrl, self::COLLECTION_MAPPING[EmbeddingEnum::RECOMMENDATION->value]),
            [
                'json' => [
                    'query' => [
                        'recommend' => [
                            'positive' => $embeddings,
                        ],
                    ],
                    'limit' => $top,
                    'with_payload' => true,
                ],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to search: '.$response->getContent(false));
        }

        return $response->toArray()['result']['points'] ?? [];
    }

    public function search(array $embedding, int $top = 20): array
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/collections/%s/points/query', $this->baseUrl, self::COLLECTION_MAPPING[EmbeddingEnum::SEARCH->value]),
            [
                'json' => [
                    'query' => $embedding,
                    'limit' => $top,
                    'with_payload' => true,
                ],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to search: '.$response->getContent(false));
        }

        return $response->toArray()['result']['points'] ?? [];
    }

    public function getCollectionInfo(EmbeddingEnum $type): array
    {
        $response = $this->client->request('GET', \sprintf('%s/collections/%s', $this->baseUrl, self::COLLECTION_MAPPING[$type->value]));

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to get collection info: '.$response->getContent(false));
        }

        return $response->toArray();
    }

    public function getPointCount(EmbeddingEnum $type): int
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/collections/%s/points/count', $this->baseUrl, self::COLLECTION_MAPPING[$type->value]),
            [
                'json' => (object) [],
            ],
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to count points: '.$response->getContent(false));
        }

        $data = $response->toArray();

        return $data['result']['count'] ?? 0;
    }
}

