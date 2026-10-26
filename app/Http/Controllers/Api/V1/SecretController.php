<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Support\Secrets;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** إدارة مفاتيح الخدمات (للمشرف فقط): الحالة، والحفظ، والحذف. لا تُعاد القيمة المخزَّنة، بل آخر 4 أحرف تلميحًا. */
class SecretController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403, 'للمشرف فقط.');

        return response()->json(['data' => Secrets::status()]);
    }

    public function update(Request $request, string $name): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403, 'للمشرف فقط.');
        abort_unless(isset(Secrets::NAMES[$name]), 404);
        $meta = Secrets::NAMES[$name];
        $value = trim((string) $request->validate(['value' => ['required', 'string', 'max:300', 'regex:'.$meta['pattern']]], [
            'value.regex' => "الصيغة غير صحيحة. المتوقع: {$meta['example']} (انسخها بلا مسافات).",
            'value.required' => 'أدخل القيمة.',
        ])['value']);
        Secrets::put($name, $value, (int) $request->user()->getAuthIdentifier());

        return response()->json(['data' => Secrets::status()]);
    }

    public function destroy(Request $request, string $name): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403, 'للمشرف فقط.');
        abort_unless(isset(Secrets::NAMES[$name]), 404);
        Secrets::forget($name);

        return response()->json(['data' => Secrets::status()]);
    }
}
