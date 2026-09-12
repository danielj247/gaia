<?php

declare(strict_types=1);

namespace App\Http\Controllers\Graph;

use App\Actions\ExpandNeighborhood;
use App\Http\Requests\NeighborhoodRequest;
use Illuminate\Http\JsonResponse;

final readonly class NeighborhoodController
{
    public function show(string $id, NeighborhoodRequest $request, ExpandNeighborhood $expand): JsonResponse
    {
        return response()->json(
            $expand->handle(
                $id,
                $request->integer('hops', 1),
                $request->integer('limit', 200),
            ),
        );
    }
}
