<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;

class PlatformAdminMiddleware
{
    public function handle(Request $request, callable $next, array $parameters = []): mixed
    {
        if (!Auth::isPlatformAdmin()) {
            throw new HttpException(403, 'You do not have permission to access the platform administration area.');
        }

        return $next($request);
    }
}
