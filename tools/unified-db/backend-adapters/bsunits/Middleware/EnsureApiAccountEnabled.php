<?php
namespace App\Http\Middleware;

use Closure;

class EnsureApiAccountEnabled
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return response()->json(['error' => ['code' => 401, 'message' => 'Unauthenticated.']], 401);
        if (!$user->active || !$user->verified) {
            return response()->json(['error' => ['code' => 401, 'message' => !$user->active
                ? 'Your account has been deactivated.' : 'Your account is not verified.']], 401);
        }
        return $next($request);
    }
}
