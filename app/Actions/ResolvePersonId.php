<?php

declare(strict_types=1);

namespace App\Actions;

use App\Graph\GraphClient;
use App\Models\PersonKey;
use Illuminate\Support\Str;

final readonly class ResolvePersonId
{
    public function __construct(private GraphClient $graph) {}

    public function handle(string $sourceId): string
    {
        $sourceId = mb_trim($sourceId);

        $existing = PersonKey::query()->where('source_id', $sourceId)->first();

        if ($existing instanceof PersonKey) {
            return $existing->gaia_id;
        }

        $fromGraph = $this->graph->findPersonId($sourceId);
        $gaiaId = $fromGraph ?? (string) Str::uuid();

        return PersonKey::query()->firstOrCreate(
            ['source_id' => $sourceId],
            ['gaia_id' => $gaiaId],
        )->gaia_id;
    }
}
