<?php

declare(strict_types=1);

namespace App\Http\Controllers\Graph;

use App\Actions\LoadDumpProgress;
use App\Models\Dump;
use Illuminate\Http\JsonResponse;

final readonly class DumpProgressController
{
    public function show(Dump $dump, LoadDumpProgress $progress): JsonResponse
    {
        return response()->json($progress->handle($dump));
    }
}
