<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to one or more of the two roles the system knows.
 *
 * Aliased as `role` in bootstrap/app.php, so a route group is guarded with
 * `->middleware('role:admin')`. This is deliberately a middleware rather than a
 * policy: the admin and wali route trees are disjoint by design, so the check
 * is "which of these two doors am I allowed through", not "may this user touch
 * this particular record".
 */
class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->unauthenticated();
        }

        /** @var array<int, UserRole> $allowed */
        $allowed = [];

        foreach ($roles as $role) {
            $allowed[] = UserRole::from($role);
        }

        if (! in_array($user->role, $allowed, true)) {
            return $this->forbidden($user->role, $allowed);
        }

        return $next($request);
    }

    /**
     * No session at all, so the SPA has to log in first.
     */
    private function unauthenticated(): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Anda harus masuk terlebih dahulu.',
            'data' => null,
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Authenticated, but this role does not get to see this resource.
     *
     * @param  array<int, UserRole>  $allowed
     */
    private function forbidden(UserRole $role, array $allowed): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak. Endpoint ini hanya untuk peran '
                .collect($allowed)->map(fn (UserRole $candidate): string => $candidate->label())->implode(' atau ')
                .'.',
            'data' => [
                'required_roles' => array_map(
                    static fn (UserRole $candidate): string => $candidate->value,
                    $allowed,
                ),
                'your_role' => $role->value,
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
