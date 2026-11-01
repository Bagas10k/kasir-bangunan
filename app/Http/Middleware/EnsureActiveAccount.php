<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

final class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            // Read storage, not a potentially stale guard instance.
            $user = User::find(Auth::id());
            $stamp = $request->session()->get('v2_auth_stamp');
            $current = $user ? [(int) $user->id, (int) $user->auth_version] : null;
            $missingAllowed = $user && ((int) $user->auth_version === 1 || Auth::viaRemember());
            if (!$user || !$user->is_active || ($stamp !== null && $stamp !== $current) || ($stamp === null && !$missingAllowed)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                if ($request->expectsJson()) return response()->json(['message'=>'Sesi berakhir. Silakan login kembali.'], 401);
                return redirect()->route('login')->withErrors(['email'=>'Sesi berakhir. Silakan login kembali.']);
            }
            Auth::setUser($user);
            $request->session()->put('v2_auth_stamp', $current);
        }
        return $next($request);
    }
}
