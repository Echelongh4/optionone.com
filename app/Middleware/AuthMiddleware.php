<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;

class AuthMiddleware
{
    public function handle(Request $request, callable $next, array $parameters = []): mixed
    {
        if (Auth::guest()) {
            Session::flash('error', 'Please sign in to continue.');
            redirect_to('login');
        }

        return $next($request);
    }
}