<?php

namespace App\Http\Middleware;

use App\Models\Barbearia;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the active tenant for the current request into the container as
 * 'barbearia.id' / 'barbearia', consumed by the BelongsToBarbearia global scope.
 *
 * Resolution order:
 *  1. Route parameter {barbearia} (slug), for public booking routes (/b/{slug}).
 *  2. Authenticated user's barbearia_atual_id, for the admin panel.
 *
 * Not applied to super-admin routes, which operate across all tenants.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $barbearia = null;

        if ($slug = $request->route('barbearia')) {
            $barbearia = Barbearia::withoutGlobalScopes()->where('slug', $slug)->first();

            if (! $barbearia) {
                abort(404);
            }
        } elseif ($request->user()?->barbearia_atual_id) {
            $barbearia = Barbearia::withoutGlobalScopes()->find($request->user()->barbearia_atual_id);
        }

        if ($barbearia) {
            app()->instance('barbearia.id', $barbearia->id);
            app()->instance('barbearia', $barbearia);
            app(PermissionRegistrar::class)->setPermissionsTeamId($barbearia->id);
        }

        return $next($request);
    }
}
