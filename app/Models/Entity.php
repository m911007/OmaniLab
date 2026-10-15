<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $type country|region|group|other
 * @property string $name_en
 * @property string|null $name_ar
 */
class Entity extends Model
{
    protected $table = 'core.entities';

    protected $guarded = [];
}
