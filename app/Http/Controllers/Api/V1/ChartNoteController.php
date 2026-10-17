<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChartNote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChartNoteController extends Controller
{
    /** @return array<string, mixed> */
    private function present(ChartNote $n): array
    {
        return ['id' => $n->id, 'body' => $n->body, 'created_at' => $n->created_at?->toIso8601String(), 'user' => $n->user ? ['id' => $n->user->id, 'name' => $n->user->name] : null];
    }

    public function index(Request $request): JsonResponse
    {
        $key = (string) $request->validate(['chart_key' => ['required', 'string', 'max:300']])['chart_key'];

        return response()->json(['data' => ChartNote::with('user:id,name')->where('chart_key', $key)->orderBy('id')->get()->map(fn (ChartNote $n) => $this->present($n))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['chart_key' => ['required', 'string', 'max:300'], 'body' => ['required', 'string', 'min:2', 'max:2000']]);
        $note = ChartNote::create($data + ['user_id' => $request->user()?->getAuthIdentifier()]);

        return response()->json(['data' => $this->present($note->load('user:id,name'))], 201);
    }

    public function destroy(Request $request, ChartNote $note): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_admin || $note->user_id === $user->id, 403);
        $note->delete();

        return response()->json(['ok' => true]);
    }
}
