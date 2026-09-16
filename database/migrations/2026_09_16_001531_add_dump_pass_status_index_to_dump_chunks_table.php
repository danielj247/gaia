<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FinalizeDumpIngest counts chunks by pass and status after every chunk, and
        // DispatchDumpChunks selects pending ids by the same columns. Without this the
        // unique (dump_id, chunk_index, pass) index leaves status uncovered and MySQL
        // visits every row of a ~45k-chunk dump per call.
        Schema::table('dump_chunks', function (Blueprint $table): void {
            $table->index(['dump_id', 'pass', 'status']);
        });
    }
};
