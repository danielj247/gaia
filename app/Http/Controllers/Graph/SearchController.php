<?php

declare(strict_types=1);

namespace App\Http\Controllers\Graph;

use App\Actions\SearchEntities;
use App\Http\Requests\SearchGraphRequest;
use Illuminate\Http\JsonResponse;

final readonly class SearchController
{
    public function show(SearchGraphRequest $request, SearchEntities $search): JsonResponse
    {
        return response()->json([
            'hits' => $search->handle(
                $request->string('q')->toString(),
                $request->integer('limit', 20),
            ),
        ]);
    }
}
