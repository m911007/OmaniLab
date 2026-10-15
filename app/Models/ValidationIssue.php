<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $run_id
 * @property int $dataset_id
 * @property string $severity
 * @property string $rule
 * @property string|null $indicator_key
 * @property string|null $entity_code
 * @property string|null $period
 * @property string $message
 * @property array<string, mixed> $context
 */
class ValidationIssue extends Model
{
    protected $table = 'ops.validation_issues';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }
}
