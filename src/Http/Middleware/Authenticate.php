<?php

namespace Dawn\Http\Middleware;

use Dawn\Dawn;

class Authenticate
{
    /**
     * Handle the incoming request.
     */
    public function handle($request, $next)
    {
        return Dawn::check($request) ? $next($request) : abort(403);
    }
}
