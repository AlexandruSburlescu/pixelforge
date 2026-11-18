<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('user.type:admin'). Passes when the signed-in user's type
 * level is at least the named type's level, so new types (e.g. "support" at
 * level 30) work without touching this class.
 */
class EnsureUserType
{
    public function handle(Request $request, Closure $next, string $minimumType = 'admin'): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->hasTypeAtLeast($minimumType), 403);

        return $next($request);
    }
}
