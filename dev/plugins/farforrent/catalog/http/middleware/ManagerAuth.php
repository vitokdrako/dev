<?php namespace Farforrent\Catalog\Http\Middleware;

use Closure;
use Backend\Facades\BackendAuth;

class ManagerAuth
{
    public function handle($request, Closure $next)
    {
        $user = BackendAuth::getUser();

        if (!$user) {
            // не залогінений у /backend
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->isSuperUser() ||
            $user->hasAnyAccess([
                'farforrent.catalog.manage_orders',
                'farforrent.catalog.access_catalog',
                'farforrent.catalog.*',
            ])) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
