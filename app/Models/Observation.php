<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $indicator_id
 * @property int $entity_id
 * @property string $period
 * @property float $value
 * @property int|null $run_id
 */
class Observation extends Model
{
    protected $table = 'core.observations';

    protected $guarded = [];
}
