<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\GraphStatsData;
use App\Enums\DumpStatus;
use App\Graph\GraphClient;
use App\Models\Dump;

final readonly class LoadGraphStats
{
    public function __construct(private GraphClient $graph) {}

    public function handle(): GraphStatsData
    {
        $stats = $this->graph->stats();
        $dump = Dump::query()
            ->where('status', DumpStatus::Completed)
            ->latest()
            ->first();

        return new GraphStatsData(
            nodes: $stats['nodes'],
            edges: $stats['edges'],
            attribution: $dump === null
                ? config()->string('graph.opensanctions.attribution')
                : $dump->attribution,
            dataset: $dump?->dataset,
        );
    }
}
