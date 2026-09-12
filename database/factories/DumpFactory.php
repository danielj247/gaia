<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DumpStatus;
use App\Models\Dump;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dump>
 */
final class DumpFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'opensanctions',
            'dataset' => 'us_ofac_sdn',
            'path' => null,
            'status' => DumpStatus::Pending,
            'record_limit' => 400,
            'entities_read' => 0,
            'nodes_upserted' => 0,
            'edges_upserted' => 0,
            'attribution' => 'Entity data from OpenSanctions.',
            'error' => null,
        ];
    }
}
