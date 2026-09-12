<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\GraphSearchHitData;
use App\Graph\GraphClient;

final readonly class SearchEntities
{
    public function __construct(private GraphClient $graph) {}

    /**
     * @return list<GraphSearchHitData>
     */
    public function handle(string $query, int $limit = 20): array
    {
        return array_map(
            fn (array $hit): GraphSearchHitData => new GraphSearchHitData(
                $hit['id'],
                $hit['label'],
                $hit['caption'],
            ),
            $this->graph->search($query, max(1, min($limit, 50))),
        );
    }
}
