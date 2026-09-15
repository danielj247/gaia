<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('source_id')->unique();
            $table->uuid('gaia_id');
            $table->timestamps();

            $table->index('gaia_id');
        });
    }
};
