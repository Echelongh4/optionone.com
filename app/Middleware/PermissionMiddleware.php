<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;

class PermissionMiddleware
{
    public function handle(Request $request, callable $next, array $parameters = []): mixed
    {
        if (!Auth::hasPermission($parameters)) {
            throw new HttpException(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
