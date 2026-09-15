<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DumpStatus;
use Carbon\CarbonInterface;
use Database\Factories\DumpFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string $source
 * @property-read string $dataset
 * @property-read string|null $path
 * @property-read DumpStatus $status
 * @property-read int|null $record_limit
 * @property-read int $entities_read
 * @property-read int $nodes_upserted
 * @property-read int $edges_upserted
 * @property-read string $attribution
 * @property-read string|null $error
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Collection<int, DumpChunk> $chunks
 * @property-read Collection<int, DumpError> $errors
 */
final class Dump extends Model
{
    /**
     * @use HasFactory<DumpFactory>
     */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'source' => 'string',
            'dataset' => 'string',
            'path' => 'string',
            'status' => DumpStatus::class,
            'record_limit' => 'integer',
            'entities_read' => 'integer',
            'nodes_upserted' => 'integer',
            'edges_upserted' => 'integer',
            'attribution' => 'string',
            'error' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<DumpChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(DumpChunk::class);
    }

    /**
     * @return HasMany<DumpError, $this>
     */
    public function errors(): HasMany
    {
        return $this->hasMany(DumpError::class);
    }
}
