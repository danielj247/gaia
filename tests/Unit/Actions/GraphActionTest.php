<?php

declare(strict_types=1);

use App\Actions\EnsureGraphSchema;
use App\Actions\ExpandNeighborhood;
use App\Actions\IngestOpenSanctionsDump;
use App\Actions\LoadGraphStats;
use App\Actions\SearchEntities;
use App\Enums\DumpStatus;
use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Graph\GraphClient;
use App\Models\Dump;
use Illuminate\Support\Facades\Http;

it('ensures schema, searches, expands, and loads stats', function (): void {
    resolve(EnsureGraphSchema::class)->handle();

    $graph = app(GraphClient::class);
    $graph->mergeNode(GraphNodeLabel::Person, 'p1', ['caption' => 'Ada Example']);
    $graph->mergeEdge(GraphEdgeType::RelatedTo, GraphNodeLabel::Person, 'p1', GraphNodeLabel::Person, 'p1');

    Dump::factory()->create([
        'status' => DumpStatus::Completed,
        'dataset' => 'us_ofac_sdn',
        'attribution' => 'Fixture attribution',
    ]);

    expect(resolve(SearchEntities::class)->handle('ada'))->toHaveCount(1)
        ->and(resolve(ExpandNeighborhood::class)->handle('p1')->nodes)->not->toBeEmpty()
        ->and(resolve(LoadGraphStats::class)->handle()->dataset)->toBe('us_ofac_sdn');
});

it('falls back to the configured attribution when no dump exists', function (): void {
    $stats = resolve(LoadGraphStats::class)->handle();

    expect($stats->dataset)->toBeNull()
        ->and($stats->attribution)->toContain('OpenSanctions');
});

it('ingests a faked opensanctions dump', function (): void {
    Http::fake([
        'https://data.opensanctions.org/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl')),
            200,
        ),
    ]);

    $dump = resolve(IngestOpenSanctionsDump::class)->handle('us_ofac_sdn', 10);

    expect($dump->status)->toBe(DumpStatus::Completed)
        ->and($dump->entities_read)->toBe(3);
});
