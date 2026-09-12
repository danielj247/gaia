<?php

declare(strict_types=1);

namespace App\Http\Controllers\Graph;

use App\Actions\LoadGraphStats;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ExplorerController
{
    public function show(LoadGraphStats $stats): Response
    {
        return Inertia::render('graph/Explorer', [
            'stats' => $stats->handle(),
        ]);
    }
}
