<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\DumpErrorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $dump_id
 * @property-read string|null $dump_chunk_id
 * @property-read int|null $line_number
 * @property-read string $code
 * @property-read string $message
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Dump $dump
 * @property-read DumpChunk|null $chunk
 */
final class DumpError extends Model
{
    /**
     * @use HasFactory<DumpErrorFactory>
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
            'dump_chunk_id' => 'string',
            'line_number' => 'integer',
            'code' => 'string',
            'message' => 'string',
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
     * @return BelongsTo<DumpChunk, $this>
     */
    public function chunk(): BelongsTo
    {
        return $this->belongsTo(DumpChunk::class, 'dump_chunk_id');
    }
}
