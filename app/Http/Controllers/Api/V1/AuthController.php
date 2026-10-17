<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** @return array{id: int, name: string, email: string, is_admin: bool}|null */
    private function payload(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_admin' => $user->is_admin] : null;
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        return response()->json(['user' => $this->payload($user)]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'password' => ['required', 'string', 'max:200']]);
        $throttleKey = Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json(['message' => 'محاولات كثيرة. حاول بعد '.RateLimiter::availableIn($throttleKey).' ثانية.'], 429);
        }
        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json(['message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.'], 422);
        }
        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        /** @var User $user */
        $user = Auth::user();

        return response()->json(['user' => $this->payload($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['user' => null]);
    }
}
