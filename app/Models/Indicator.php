<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $key
 * @property int $dataset_id
 * @property string $name_ar
 * @property string $name_en
 * @property string|null $unit
 * @property string $frequency
 * @property string|null $description
 * @property-read Dataset $dataset
 */
class Indicator extends Model
{
    protected $table = 'core.indicators';

    protected $guarded = [];

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
