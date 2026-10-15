<?php

namespace App\Models;

use App\Core\Freshness\DatasetStatus;
use App\Core\Ingestion\Contracts\Connector;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property int $source_id
 * @property string $name_ar
 * @property string $name_en
 * @property string $section
 * @property class-string<Connector> $connector
 * @property string $reliability
 * @property int $interval_minutes
 * @property bool $enabled
 * @property DatasetStatus $status
 * @property string|null $source_version
 * @property CarbonImmutable|null $source_published_at
 * @property string|null $probe_token
 * @property string|null $payload_hash
 * @property CarbonImmutable|null $last_checked_at
 * @property CarbonImmutable|null $last_changed_at
 * @property CarbonImmutable|null $last_success_at
 * @property CarbonImmutable|null $next_check_at
 * @property int $consecutive_failures
 * @property string|null $last_error
 * @property-read Source $source
 */
class Dataset extends Model
{
    protected $table = 'ops.datasets';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DatasetStatus::class,
            'enabled' => 'boolean',
            'source_published_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'last_changed_at' => 'immutable_datetime',
            'last_success_at' => 'immutable_datetime',
            'next_check_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /** @return HasMany<Indicator, $this> */
    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class);
    }

    /** @return HasMany<IngestRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(IngestRun::class)->latest('started_at');
    }
}
