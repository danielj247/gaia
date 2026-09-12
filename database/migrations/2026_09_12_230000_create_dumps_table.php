<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dumps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source');
            $table->string('dataset');
            $table->string('path')->nullable();
            $table->string('status');
            $table->unsignedInteger('record_limit')->nullable();
            $table->unsignedInteger('entities_read')->default(0);
            $table->unsignedInteger('nodes_upserted')->default(0);
            $table->unsignedInteger('edges_upserted')->default(0);
            $table->text('attribution');
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
};
