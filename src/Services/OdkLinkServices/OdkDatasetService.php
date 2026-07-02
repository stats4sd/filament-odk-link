<?php

namespace Stats4sd\FilamentOdkLink\Services\OdkLinkServices;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;

/**
 * Wraps ODK Central's Entity Management API (Entity Lists / Datasets and their Entities).
 * Every method is scoped by an OdkProject and a dataset name only - nothing here is specific
 * to any one entity type, so it can be reused for any ODK-entity-backed data type.
 */
trait OdkDatasetService
{
    /**
     * Creates a new Entity List (Dataset) in ODK Central under the given project.
     *
     * @throws RequestException|ConnectionException
     */
    public function createOdkDataset(OdkProject $odkProject, string $name): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->post("{$this->endpoint}/projects/{$odkProject->id}/datasets", [
                'name' => $name,
            ])
            ->throw()
            ->json();
    }

    /**
     * @throws RequestException|ConnectionException
     */
    public function getOdkDataset(OdkProject $odkProject, string $name): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->get("{$this->endpoint}/projects/{$odkProject->id}/datasets/{$name}")
            ->throw()
            ->json();
    }

    /**
     * Adds a property to a Dataset's schema. Safe to call for a property that already
     * exists - Central treats re-adding the same property name as a no-op.
     *
     * @throws RequestException|ConnectionException
     */
    public function addOdkDatasetProperty(OdkProject $odkProject, string $datasetName, string $propertyName): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->post("{$this->endpoint}/projects/{$odkProject->id}/datasets/{$datasetName}/properties", [
                'name' => $propertyName,
            ])
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, string>  $data
     *
     * @throws RequestException|ConnectionException
     */
    public function createOdkEntity(OdkProject $odkProject, string $datasetName, string $label, array $data, ?string $uuid = null): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->post("{$this->endpoint}/projects/{$odkProject->id}/datasets/{$datasetName}/entities", array_filter([
                'uuid' => $uuid,
                'label' => $label,
                'data' => $data,
            ]))
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, string>  $data
     *
     * @throws RequestException|ConnectionException
     */
    public function updateOdkEntity(OdkProject $odkProject, string $datasetName, string $uuid, string $label, array $data, int $baseVersion): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->patch("{$this->endpoint}/projects/{$odkProject->id}/datasets/{$datasetName}/entities/{$uuid}?baseVersion={$baseVersion}", [
                'label' => $label,
                'data' => $data,
            ])
            ->throw()
            ->json();
    }

    /**
     * Soft-deletes an entity in ODK Central.
     *
     * @throws RequestException|ConnectionException
     */
    public function deleteOdkEntity(OdkProject $odkProject, string $datasetName, string $uuid): bool
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->delete("{$this->endpoint}/projects/{$odkProject->id}/datasets/{$datasetName}/entities/{$uuid}")
            ->throw()
            ->successful();
    }

    /**
     * @param  array<int, array{uuid?: string, label: string, data: array<string, string>}>  $entities
     *
     * @throws RequestException|ConnectionException
     */
    public function bulkCreateOdkEntities(OdkProject $odkProject, string $datasetName, array $entities, ?string $sourceName = null): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->post("{$this->endpoint}/projects/{$odkProject->id}/datasets/{$datasetName}/entities", array_filter([
                'entities' => $entities,
                'source' => $sourceName ? ['name' => $sourceName] : null,
            ]))
            ->throw()
            ->json();
    }

    /**
     * Fetches the current data for every non-deleted entity in a dataset via Central's OData feed.
     * This is the "live read" source used instead of trusting a background-synced local copy.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws RequestException|ConnectionException
     */
    public function getOdkDatasetEntitiesFeed(OdkProject $odkProject, string $datasetName): array
    {
        $token = $this->authenticate();

        $response = Http::withToken($token)
            ->get("{$this->endpoint}/projects/{$odkProject->id}/datasets/{$datasetName}.svc/Entities")
            ->throw()
            ->json();

        return $response['value'] ?? [];
    }
}
