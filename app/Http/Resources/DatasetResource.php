<?php

namespace App\Http\Resources;

use App\Models\Dataset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Dataset */
class DatasetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'section' => $this->section,
            'status' => $this->status->value,
            'status_label' => $this->status->labelAr(),
            'reliability' => $this->reliability,
            'source' => [
                'key' => $this->source->key,
                'name_ar' => $this->source->name_ar,
                'name_en' => $this->source->name_en,
                'url' => $this->source->url,
                'license' => $this->source->license,
            ],
            'freshness' => [
                'source_version' => $this->source_version,
                'source_published_at' => $this->source_published_at?->toIso8601String(),
                'last_checked_at' => $this->last_checked_at?->toIso8601String(),
                'last_changed_at' => $this->last_changed_at?->toIso8601String(),
                'last_success_at' => $this->last_success_at?->toIso8601String(),
                'next_check_at' => $this->next_check_at?->toIso8601String(),
                'interval_minutes' => $this->interval_minutes,
            ],
            'indicators' => $this->whenLoaded('indicators', fn () => $this->indicators->map(fn ($i) => [
                'key' => $i->key, 'name_ar' => $i->name_ar, 'name_en' => $i->name_en, 'unit' => $i->unit, 'frequency' => $i->frequency,
            ])),
        ];
    }
}
