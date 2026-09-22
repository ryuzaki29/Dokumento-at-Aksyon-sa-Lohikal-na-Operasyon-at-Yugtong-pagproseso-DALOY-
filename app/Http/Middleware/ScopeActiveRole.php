<?php

namespace App\Http\Middleware;

use App\Support\ActiveRoleManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScopeActiveRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            ActiveRoleManager::apply($user);
        }

        return $next($request);
    }
}
