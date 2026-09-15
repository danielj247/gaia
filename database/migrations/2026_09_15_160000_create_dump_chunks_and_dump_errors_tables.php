<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dump_chunks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('dump_id')->constrained('dumps')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->string('pass');
            $table->unsignedInteger('line_start');
            $table->unsignedInteger('line_end');
            $table->unsignedBigInteger('byte_offset');
            $table->string('status');
            $table->unsignedInteger('entities_read')->default(0);
            $table->unsignedInteger('nodes_upserted')->default(0);
            $table->unsignedInteger('edges_upserted')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['dump_id', 'chunk_index', 'pass']);
        });

        Schema::create('dump_errors', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('dump_id')->constrained('dumps')->cascadeOnDelete();
            $table->foreignUuid('dump_chunk_id')->nullable()->constrained('dump_chunks')->nullOnDelete();
            $table->unsignedInteger('line_number')->nullable();
            $table->string('code');
            $table->text('message');
            $table->timestamps();
        });
    }
};
