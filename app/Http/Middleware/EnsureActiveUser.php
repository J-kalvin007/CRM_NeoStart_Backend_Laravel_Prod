<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        abort_if($request->user()?->status === false, 403, 'Votre compte est désactivé. Contactez votre administrateur.');
        return $next($request);
    }
}
