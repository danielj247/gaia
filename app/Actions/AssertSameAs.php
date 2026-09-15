<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Graph\GraphClient;
use InvalidArgumentException;

final readonly class AssertSameAs
{
    public function __construct(private GraphClient $graph) {}

    public function handle(string $leftId, string $rightId, string $decidedBy): void
    {
        $left = $this->graph->findPersonId(mb_trim($leftId));
        $right = $this->graph->findPersonId(mb_trim($rightId));

        if ($left === null || $right === null) {
            throw new InvalidArgumentException('SAME_AS requires two existing people.');
        }

        if ($left === $right) {
            throw new InvalidArgumentException('SAME_AS requires two distinct people.');
        }

        $this->graph->mergeEdge(
            GraphEdgeType::SameAs,
            GraphNodeLabel::Person,
            $left,
            GraphNodeLabel::Person,
            $right,
            [
                'method' => 'manual',
                'decidedBy' => $decidedBy,
            ],
        );
    }
}
