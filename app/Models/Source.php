<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $name_ar
 * @property string $name_en
 * @property string|null $publisher
 * @property string|null $url
 * @property string|null $license
 * @property string $reliability
 */
class Source extends Model
{
    protected $table = 'core.sources';

    protected $guarded = [];
}
