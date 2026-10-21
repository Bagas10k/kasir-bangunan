<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        $csrf = csrf_token();
        $action = route('login');
        $errorMessage = '';
        if (session('errors') && session('errors')->has('email')) {
            $msg = htmlspecialchars(session('errors')->first('email'), ENT_QUOTES, 'UTF-8');
            $errorMessage = '<div style="color:#b00; font-size:13px; margin-bottom:14px; font-weight:bold;">' . $msg . '</div>';
        }

        $html = '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Kasir Bangunan</title>
    <style>
        body { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; background: #fafafa; color: #111; margin: 0; padding: 40px 16px; display: flex; justify-content: center; align-items: center; min-height: 80vh; }
        .card { background: #fff; border: 1px solid #ddd; padding: 24px; border-radius: 4px; width: 100%; max-width: 380px; box-sizing: border-box; }
        h1 { font-size: 16px; margin-top: 0; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 16px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; margin-bottom: 6px; text-transform: uppercase; font-weight: bold; }
        input { width: 100%; min-height: 44px; box-sizing: border-box; padding: 10px 12px; font-family: inherit; font-size: 14px; border: 1px solid #999; border-radius: 3px; background: #fff; color: #111; }
        input:focus { outline: 2px solid #111; outline-offset: 2px; }
        .btn { width: 100%; min-height: 44px; background: #111; color: #fff; border: 1px solid #111; padding: 10px 16px; font-family: inherit; font-size: 14px; font-weight: bold; cursor: pointer; text-transform: uppercase; border-radius: 3px; }
        .btn:hover { background: #333; }
        .btn:focus { outline: 2px solid #111; outline-offset: 2px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>LOGIN KASIR</h1>
        ' . $errorMessage . '
        <form method="POST" action="' . $action . '">
            <input type="hidden" name="_token" value="' . $csrf . '">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Masuk</button>
        </form>
    </div>
</body>
</html>';

        return response($html);
    }

    public function login(Request $request)
    {
        $throttleKey = Str::transliterate(Str::lower($request->input('email')) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            RateLimiter::clear($throttleKey);
            $intended = $request->session()->get('url.intended');
            if ($intended === '/' || $intended === '') {
                $request->session()->forget('url.intended');
            }
            return redirect()->intended(route('pos.index'));
        }

        RateLimiter::hit($throttleKey, 60);

        return back()->withErrors([
            'email' => 'Kredensial tidak valid.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
