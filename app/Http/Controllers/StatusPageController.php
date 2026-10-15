<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\IngestRun;
use Illuminate\Contracts\View\View;

/** صفحة «حالة البيانات»: كل مجموعة ومتى فُحصت وتغيّرت آخر مرة. */
class StatusPageController extends Controller
{
    public function __invoke(): View
    {
        return view('status', [
            'datasets' => Dataset::with('source')->orderBy('section')->orderBy('key')->get(),
            'runs' => IngestRun::with('dataset')->latest('id')->limit(15)->get(),
        ]);
    }
}
