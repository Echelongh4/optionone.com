<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;

class GuestMiddleware
{
    public function handle(Request $request, callable $next, array $parameters = []): mixed
    {
        if (Auth::check()) {
            redirect_to(Auth::isPlatformAdmin() ? 'platform' : 'dashboard');
        }

        return $next($request);
    }
}
