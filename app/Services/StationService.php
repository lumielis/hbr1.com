<?php

namespace App\Services;

/**
 * Station catalog.
 *
 * Wraps the configured station list and is the only place that knows how a
 * station is looked up. Legacy code keyed stations by a numeric index and by
 * the `Id` string; the public URL uses a lowercase slug.
 */
class StationService
{
    /** @var array<string, array> keyed by lowercase slug */
    private array $bySlug = [];

    /** @var array<string, array> keyed by the canonical Id, e.g. "Trance" */
    private array $byId = [];

    public function __construct(array $stations)
    {
        foreach ($stations as $station) {
            $this->bySlug[strtolower($station['slug'])] = $station;
            $this->byId[$station['id']] = $station;
        }
    }

    /** @return array<int, array> */
    public function all(): array
    {
        return array_values($this->bySlug);
    }

    /**
     * Resolve a station by its URL slug (case-insensitive) or its canonical Id.
     */
    public function find(string $identifier): ?array
    {
        $key = strtolower($identifier);

        return $this->bySlug[$key] ?? $this->byId[$identifier] ?? null;
    }

    public function findOrFail(string $identifier): array
    {
        $station = $this->find($identifier);

        if ($station === null) {
            throw new \RuntimeException("Unknown station: {$identifier}");
        }

        return $station;
    }

    public function slug(array $station): string
    {
        return $station['slug'];
    }
}
