<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\ValueChain\UploadParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** رفع سلاسل أسعار دورية بملف Excel/CSV (للمصادر بلا واجهة برمجية مثل GASTAT). للمحلل ومدير النظام. */
class UploadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user(), 401);

        return response()->json([
            'uploads' => DB::table('intel.uploads as u')->leftJoin('app.users as us', 'us.id', '=', 'u.uploaded_by')->orderByDesc('u.id')->limit(30)->get(['u.*', 'us.name as uploader']),
            'series' => DB::table('intel.uploaded_series as s')->selectRaw('s.*, (SELECT count(*) FROM intel.uploaded_points p WHERE p.series_id = s.id) AS points, (SELECT max(period) FROM intel.uploaded_points p WHERE p.series_id = s.id) AS last_period')->orderBy('s.source_name')->orderBy('s.label_ar')->get(),
        ]);
    }

    public function store(Request $request, UploadParser $parser): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user && $user->canEditChains(), 403, 'الرفع للمحلل ومدير النظام.');
        $d = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream'],
            'source_name' => ['required', 'string', 'max:160'], 'source_url' => ['nullable', 'url:https', 'max:600'],
            'entity_code' => ['required', 'regex:/^[A-Z]{3}$/'], 'unit' => ['nullable', 'string', 'max:40'], 'currency' => ['nullable', 'regex:/^[A-Z]{3}$/'],
            'frequency' => ['required', Rule::in(['D', 'W', 'M', 'Q', 'A'])], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'xlsx'], true)) {
            throw ValidationException::withMessages(['file' => 'الصيغ المقبولة: xlsx أو csv.']);
        }
        try {
            $parsed = $parser->parse($file->getRealPath(), $ext, $d['frequency']);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $result = DB::transaction(function () use ($d, $file, $parsed, $user) {
            $uploadId = DB::table('intel.uploads')->insertGetId(['source_name' => $d['source_name'], 'source_url' => $d['source_url'] ?? null, 'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'rows' => array_sum(array_map('count', $parsed['series'])), 'notes' => $d['notes'] ?? null, 'uploaded_by' => $user->id, 'created_at' => now()]);
            $out = [];
            foreach ($parsed['series'] as $label => $points) {
                $key = 'upload.'.Str::slug(Str::ascii($d['source_name']), '_').'.'.(Str::slug(Str::ascii($label), '_') ?: substr(md5($label), 0, 8));
                $key = mb_substr(strtolower($key), 0, 128);
                $seriesId = DB::table('intel.uploaded_series')->where('key', $key)->value('id');
                $meta = ['label_ar' => mb_substr($label, 0, 200), 'source_name' => $d['source_name'], 'entity_code' => $d['entity_code'], 'unit' => $d['unit'] ?? null, 'currency' => $d['currency'] ?? null, 'frequency' => $d['frequency'], 'updated_at' => now()];
                if ($seriesId) {
                    DB::table('intel.uploaded_series')->where('id', $seriesId)->update($meta);
                } else {
                    $seriesId = DB::table('intel.uploaded_series')->insertGetId($meta + ['key' => $key, 'created_at' => now()]);
                }
                foreach ($points as $period => $value) {
                    DB::table('intel.uploaded_points')->updateOrInsert(['series_id' => $seriesId, 'period' => $period], ['value' => $value, 'upload_id' => $uploadId, 'updated_at' => now()]);
                }
                $out[] = ['key' => $key, 'label_ar' => $label, 'points' => count($points), 'first' => array_key_first($points), 'last' => array_key_last($points)];
            }

            return $out;
        });

        return response()->json(['series' => $result, 'warnings' => $parsed['warnings']], 201);
    }
}
