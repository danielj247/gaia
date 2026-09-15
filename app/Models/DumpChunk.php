<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DumpChunkPass;
use App\Enums\DumpChunkStatus;
use Carbon\CarbonInterface;
use Database\Factories\DumpChunkFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string $dump_id
 * @property-read int $chunk_index
 * @property-read DumpChunkPass $pass
 * @property-read int $line_start
 * @property-read int $line_end
 * @property-read int $byte_offset
 * @property-read DumpChunkStatus $status
 * @property-read int $entities_read
 * @property-read int $nodes_upserted
 * @property-read int $edges_upserted
 * @property-read string|null $error
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Dump $dump
 * @property-read Collection<int, DumpError> $errors
 */
final class DumpChunk extends Model
{
    /**
     * @use HasFactory<DumpChunkFactory>
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
            'dump_id' => 'string',
            'chunk_index' => 'integer',
            'pass' => DumpChunkPass::class,
            'line_start' => 'integer',
            'line_end' => 'integer',
            'byte_offset' => 'integer',
            'status' => DumpChunkStatus::class,
            'entities_read' => 'integer',
            'nodes_upserted' => 'integer',
            'edges_upserted' => 'integer',
            'error' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Dump, $this>
     */
    public function dump(): BelongsTo
    {
        return $this->belongsTo(Dump::class);
    }

    /**
     * @return HasMany<DumpError, $this>
     */
    public function errors(): HasMany
    {
        return $this->hasMany(DumpError::class);
    }
}
