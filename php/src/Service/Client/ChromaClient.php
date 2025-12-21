<?php

namespace App\Service\Client;

use App\Enum\EmbeddingEnum;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChromaClient
{
    public const TENANT = 'fyc';
    public const DATABASE = 'fyc_db';
    public const COLLECTION_ID_FILE_PATH = __DIR__.'/../../../var/chroma_collection_id.txt';

    private HttpClientInterface $client;
    private string $baseUrl;

    public function __construct(HttpClientInterface $client, string $baseUrl)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
    }

    public function initTenant(): static
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/tenants', $this->baseUrl),
            [
                'json' => [
                    'name' => self::TENANT,
                ],
            ]
        );

        if (200 !== $response->getStatusCode() && 409 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to create tenant');
        }

        return $this;
    }

    public function initDatabase(): static
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/tenants/%s/databases', $this->baseUrl, self::TENANT),
            [
                'json' => [
                    'name' => self::DATABASE,
                ],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to create database');
        }

        return $this;
    }

    public function initCollection(EmbeddingEnum $type): static
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/tenants/%s/databases/%s/collections', $this->baseUrl, self::TENANT, self::DATABASE),
            [
                'json' => [
                    'name' => $type->value,
                ],
            ]
        );

        $id = $response->toArray()['id'] ?? null;
        if (200 !== $response->getStatusCode() || null === $id) {
            throw new \RuntimeException('Failed to create collection');
        }

        $this->storeCollectionId($id);

        return $this;
    }

    public function reset(): static
    {
        $this->deleteCollectionId();

        $response = $this->client->request(
            'DELETE',
            \sprintf('%s/tenants/%s/databases/%s', $this->baseUrl, self::TENANT, self::DATABASE)
        );

        if (200 !== $response->getStatusCode() && 404 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to delete database');
        }

        return $this;
    }

    public function insert(array $embedding, string $document): static
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/tenants/%s/databases/%s/collections/%s/add', $this->baseUrl, self::TENANT, self::DATABASE, $this->getCollectionId()),
            [
                'json' => [
                    'documents' => [$document],
                    'ids' => [Uuid::v7()->toString()],
                    'embeddings' => [$embedding],
                ],

            ]
        );

        if (201 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to insert document');
        }

        return $this;
    }

    public function search(array $embedding, int $limit): array
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/tenants/%s/databases/%s/collections/%s/query', $this->baseUrl, self::TENANT, self::DATABASE, $this->getCollectionId()),
            [
                'json' => [
                    'query_embeddings' => [$embedding],
                    'n_results' => $limit,
                ],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to search document');
        }

        return $response->toArray()['documents'] ?? [];
    }

    private function storeCollectionId(string $id): void
    {
        file_put_contents(self::COLLECTION_ID_FILE_PATH, $id);
    }

    private function getCollectionId(): string
    {
        return file_get_contents(self::COLLECTION_ID_FILE_PATH);
    }

    private function deleteCollectionId(): void
    {
        if (file_exists(self::COLLECTION_ID_FILE_PATH)) {
            unlink(self::COLLECTION_ID_FILE_PATH);
        }
    }
}

