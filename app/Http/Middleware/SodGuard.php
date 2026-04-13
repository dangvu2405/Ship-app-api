<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Separation of Duties guard.
 *
 * Prevents the same user from both creating and approving/confirming a resource.
 * Usage in routes:  ->middleware('sod:created_by')
 *                   ->middleware('sod:reported_by')
 *
 * The middleware resolves the route model binding (first route parameter) and
 * checks that the acting user's id does not equal the value of $creatorColumn.
 */
class SodGuard
{
    public function handle(Request $request, Closure $next, string $creatorColumn = 'created_by'): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Resolve the first route model binding
        $model = collect($request->route()->parameters())->first();

        if ($model && method_exists($model, '__get')) {
            $creatorId = $model->{$creatorColumn};

            if ($creatorId !== null && (int) $creatorId === (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Separation of Duties violation: you cannot approve/confirm a record you created.',
                ], 403);
            }
        }

        return $next($request);
    }
}
