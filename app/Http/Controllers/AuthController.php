<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
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
            $errorMessage = '<div role="alert" class="alert-box">' . $msg . '</div>';
        }

        $escapedAction = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
        $escapedCsrf = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Kasir - Sistem Bahan Bangunan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-sans: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --font-mono: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            --canvas: #f8faf8;
            --surface: #ffffff;
            --primary: #2d6a4f;
            --primary-hover: #1b4332;
            --primary-light: #d8f3dc;
            --accent: #52b788;
            --mint-surface: #e8f5e9;
            --text-main: #1c2826;
            --text-muted: #495057;
            --text-caption: #6c757d;
            --border: #d8f3dc;
            --border-card: #cbe3d3;
            --focus-ring: #2d6a4f;
            --danger: #b02a37;
            --danger-bg: #f8d7da;
            --danger-border: #f5c2c7;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-sans);
            background-color: var(--canvas);
            color: var(--text-main);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            font-feature-settings: "cv02", "cv03", "cv04", "cv11";
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .auth-card {
            width: 100%;
            max-width: 420px;
            background-color: var(--surface);
            border: 1px solid var(--border-card);
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(45, 106, 79, 0.08);
            padding: 36px 32px;
        }

        .form-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .badge-tag {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--primary);
            background-color: var(--mint-surface);
            border: 1px solid var(--border);
            padding: 4px 10px;
            border-radius: 4px;
            margin-bottom: 12px;
        }

        h1 {
            font-size: 24px;
            font-weight: 800;
            color: #1b4332;
            margin-bottom: 6px;
            letter-spacing: -0.02em;
            text-wrap: balance;
        }

        .form-subtitle {
            font-size: 13.5px;
            line-height: 1.5;
            color: #495057;
        }

        .alert-box {
            background-color: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger);
            border-radius: 6px;
            padding: 12px 14px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: -0.005em;
            margin-bottom: 6px;
            color: #1c2826;
        }

        input {
            width: 100%;
            min-height: 44px;
            padding: 10px 14px;
            font-family: inherit;
            font-size: 14px;
            border: 1px solid #ced4da;
            background-color: #ffffff;
            color: var(--text-main);
            border-radius: 6px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        input:hover {
            border-color: #adb5bd;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.2);
        }

        .btn-submit {
            width: 100%;
            min-height: 44px;
            background-color: var(--primary);
            color: #ffffff;
            border: 1px solid var(--primary);
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.01em;
            cursor: pointer;
            border-radius: 6px;
            padding: 10px 20px;
            transition: background-color 0.15s ease, border-color 0.15s ease;
            margin-top: 4px;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        .btn-submit:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.3);
        }

        .login-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 16px 0 12px;
            color: var(--text-caption);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .login-divider::before, .login-divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid var(--border);
        }

        .login-divider:not(:empty)::before {
            margin-right: 10px;
        }

        .login-divider:not(:empty)::after {
            margin-left: 10px;
        }

        .btn-demo {
            width: 100%;
            min-height: 44px;
            background-color: var(--mint-surface);
            color: var(--primary-hover);
            border: 1px solid var(--border-card);
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.01em;
            cursor: pointer;
            border-radius: 6px;
            padding: 10px 20px;
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }

        .btn-demo:hover {
            background-color: var(--primary-light);
            border-color: var(--accent);
        }

        .btn-demo:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.2);
        }

        .form-footer-note {
            margin-top: 20px;
            font-size: 12px;
            color: var(--text-caption);
            text-align: center;
            line-height: 1.5;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                transform: none !important;
            }
        }
    </style>
</head>
<body>
    <main class="auth-card" aria-labelledby="login-heading">
        <div class="form-header">
            <span class="badge-tag">Kasir Bahan Bangunan</span>
            <h1 id="login-heading">LOGIN KASIR</h1>
            <p class="form-subtitle">Silakan masukkan identitas akun untuk mengakses sistem kasir.</p>
        </div>
        ' . $errorMessage . '
        <form method="POST" action="' . $escapedAction . '">
            <input type="hidden" name="_token" value="' . $escapedCsrf . '">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" autocomplete="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn-submit">Masuk ke Kasir</button>
        </form>
        <div class="login-divider">atau pengujian langsung</div>
        <form method="POST" action="' . htmlspecialchars(route('login.demo'), ENT_QUOTES, 'UTF-8') . '">
            <input type="hidden" name="_token" value="' . $escapedCsrf . '">
            <button type="submit" class="btn-demo">Masuk Uji Coba (1-Klik)</button>
        </form>
        <div class="form-footer-note">Prototipe pengujian kasir material. Akses internal.</div>
    </main>
</body>
</html>';

        return response($html);
    }

    public function login(Request $request)
    {
        $throttleKey = Str::transliterate(Str::lower($request->input('email')) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return redirect()->route('login')->withErrors([
                'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ])->withInput($request->except('password'));
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->route('login')
                ->withErrors($validator)
                ->withInput($request->except('password'));
        }

        $credentials = $validator->validated();

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

        return redirect()->route('login')->withErrors([
            'email' => 'Kredensial tidak valid.',
        ])->withInput($request->except('password'));
    }

    public function demoLogin(Request $request)
    {
        $user = User::first();
        if (! $user) {
            $user = User::create([
                'name' => 'Operator Kasir Toko',
                'email' => 'kasir@jajandigital.web.id',
                'password' => Hash::make(Str::random(32)),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('pos.index'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}