<?php

declare(strict_types=1);

namespace App\Http\Controllers\Graph;

use App\Actions\LoadDumpProgress;
use App\Actions\LoadDumps;
use App\Models\Dump;
use Inertia\Inertia;
use Inertia\Response;

final readonly class DumpController
{
    public function index(LoadDumps $dumps): Response
    {
        return Inertia::render('graph/Dumps', [
            'dumps' => $dumps->handle(),
        ]);
    }

    public function show(Dump $dump, LoadDumpProgress $progress): Response
    {
        return Inertia::render('graph/DumpStatus', [
            'dump' => $progress->handle($dump),
        ]);
    }
}
