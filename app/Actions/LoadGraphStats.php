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
        $datasets = [];

        foreach (
            Dump::query()
                ->where('status', DumpStatus::Completed)
                ->orderBy('dataset')
                ->pluck('dataset') as $dataset
        ) {
            if (! is_string($dataset) || $dataset === '' || in_array($dataset, $datasets, true)) {
                continue;
            }

            $datasets[] = $dataset;
        }

        return new GraphStatsData(
            nodes: $stats['nodes'],
            edges: $stats['edges'],
            attribution: config()->string('graph.opensanctions.attribution'),
            dataset: $datasets === [] ? null : implode(', ', $datasets),
            datasets: $datasets,
        );
    }
}
