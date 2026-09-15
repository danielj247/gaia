<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dump;
use App\Models\DumpError;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DumpError>
 */
final class DumpErrorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dump_id' => Dump::factory(),
            'dump_chunk_id' => null,
            'line_number' => 1,
            'code' => 'invalid_json',
            'message' => 'Invalid JSON.',
        ];
    }
}
