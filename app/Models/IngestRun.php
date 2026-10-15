<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $dataset_id
 * @property string $status running|unchanged|staged|quarantined|promoted|failed
 * @property string $trigger
 * @property string|null $probe_token
 * @property string|null $payload_hash
 * @property int $records_count
 * @property int $promoted_count
 * @property string|null $error
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property-read Dataset $dataset
 */
class IngestRun extends Model
{
    protected $table = 'ops.ingest_runs';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /** @return HasMany<ValidationIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(ValidationIssue::class, 'run_id');
    }
}
