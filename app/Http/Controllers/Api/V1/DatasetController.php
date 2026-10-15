<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DatasetResource;
use App\Models\Dataset;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DatasetController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return DatasetResource::collection(Dataset::with('source')->where('enabled', true)->orderBy('section')->orderBy('key')->get());
    }

    public function show(string $key): DatasetResource
    {
        $dataset = Dataset::with(['source', 'indicators'])->where('key', $key)->firstOrFail();

        return new DatasetResource($dataset);
    }
}
