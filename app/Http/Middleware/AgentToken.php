<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** يحمي واجهة الوكيل برمز Bearer ثابت من البيئة (AGENT_TOKEN). بلا رمز مضبوط تُعطَّل الواجهة كليًا. */
final class AgentToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('omanilab.agent_token');
        abort_if($expected === '', 503, 'واجهة الوكيل معطّلة: لم يُضبط AGENT_TOKEN.');
        abort_unless(hash_equals($expected, (string) $request->bearerToken()), 401, 'رمز الوكيل غير صحيح.');

        return $next($request);
    }
}
