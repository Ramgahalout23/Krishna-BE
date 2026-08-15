<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        // API-only app: always return null so Laravel sends a JSON 401 response.
        // This avoids RouteNotFoundException when no 'admin.login' route exists.
        return null;
    }
}
