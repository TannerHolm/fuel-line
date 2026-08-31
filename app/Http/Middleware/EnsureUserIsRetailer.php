<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsRetailer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Founders may view the portal too (to see what partners see).
        abort_unless($user && ($user->role->value === 'retailer' || $user->isFounder()), 403);

        return $next($request);
    }
}
