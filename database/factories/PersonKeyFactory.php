<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PersonKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PersonKey>
 */
final class PersonKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_id' => 'ofac-'.fake()->unique()->numerify('####'),
            'gaia_id' => (string) Str::uuid(),
        ];
    }
}
