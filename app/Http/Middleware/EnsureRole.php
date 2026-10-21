<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (in_array($user->role, $roles, true)) {
            return $next($request);
        }

        // Selling is a capability, not a fixed identity: any signed in buyer
        // who holds an organizer profile may use the seller workspace, so one
        // account can both buy and sell without being locked into a single role.
        if (in_array('organizer', $roles, true) && $user->isOrganizer()) {
            return $next($request);
        }

        abort(403, 'You do not have access to this area.');
    }
}
