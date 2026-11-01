<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class V2Cutover
{
    public function handle(Request $request, Closure $next)
    {
        if (config('v2.enabled') && $request->is('demo-setup', 'demo-setup/*')) { abort(404); }
        if (!config('v2.enabled') || $request->is('v2', 'v2/*', 'login', 'logout', 'up')) {
            return $next($request);
        }
        if (!Auth::check()) {
            return $next($request);
        }
        $role = app(\App\Services\V2\Operations::class)->role(Auth::id());
        if ($request->path() === '/') {
            return redirect()->route('v2.index');
        }
        // Legacy remains an admin read-only archive after cutover.
        abort_unless($role === 'admin' && $request->isMethod('GET'), 403, 'Gunakan alur V2; arsip lama hanya dapat dibaca admin.');
        return $next($request);
    }
}
