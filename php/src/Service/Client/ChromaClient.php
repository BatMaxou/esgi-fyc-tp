<?php

namespace App\Service\Client;

use App\Enum\EmbeddingEnum;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChromaClient
{
    public const TENANT = 'fyc';
    public const DATABASE = 'fyc_db';

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

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to create collection');
        }

        return $this;
    }

    public function reset(): static
    {
        $response = $this->client->request(
            'DELETE',
            \sprintf('%s/tenants/%s/databases/%s', $this->baseUrl, self::TENANT, self::DATABASE)
        );

        if (200 !== $response->getStatusCode() && 404 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to delete database');
        }

        return $this;
    }

    public function insert(string $id, EmbeddingEnum $type, array $embedding): static
    {
        $response = $this->client->request(
            'POST',
            \sprintf('%s/tenants/%s/databases/%s/collections/%s/add', $this->baseUrl, self::TENANT, self::DATABASE, $type->value),
            [
                'json' => [
                    'documents' => ['test'],
                    'ids' => [$id],
                    'embeddings' => [$embedding],
                ],

            ]
        );

        dd($response->toArray());

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Failed to create collection');
        }

        return $this;
    }
}

