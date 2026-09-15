<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DumpChunkPass;
use App\Enums\DumpChunkStatus;
use App\Models\Dump;
use App\Models\DumpChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DumpChunk>
 */
final class DumpChunkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dump_id' => Dump::factory(),
            'chunk_index' => 0,
            'pass' => DumpChunkPass::Entities,
            'line_start' => 1,
            'line_end' => 1,
            'byte_offset' => 0,
            'status' => DumpChunkStatus::Pending,
            'entities_read' => 0,
            'nodes_upserted' => 0,
            'edges_upserted' => 0,
            'error' => null,
        ];
    }
}
