<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** شرح معدَّل لرسم يحل محل الشرح الافتراضي. */
class ChartExplanation extends Model
{
    protected $table = 'app.chart_explanations';

    protected $primaryKey = 'chart_key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['chart_key', 'explanation', 'edited_by', 'updated_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['explanation' => 'array', 'updated_at' => 'datetime'];
    }
}
