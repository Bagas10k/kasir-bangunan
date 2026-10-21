<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    <style>
        :root {
            --canvas: #f8faf8;
            --surface: #ffffff;
            --surface-subtle: #f0f7f3;
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
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--canvas);
            color: var(--text-main);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            -webkit-font-smoothing: antialiased;
        }

        .auth-container {
            width: 100%;
            max-width: 860px;
            background-color: var(--surface);
            border: 1px solid var(--border-card);
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(45, 106, 79, 0.08);
            overflow: hidden;
        }

        .auth-grid {
            display: flex;
            flex-direction: column;
        }

        .panel-form {
            order: 1;
            padding: 36px 30px;
        }

        .panel-info {
            order: 2;
            background-color: var(--surface-subtle);
            border-top: 1px solid var(--border-card);
            padding: 28px 24px;
            font-size: 13px;
        }

        @media (min-width: 768px) {
            .auth-grid {
                display: grid;
                grid-template-columns: 1fr 1.15fr;
            }

            .panel-info {
                order: 1;
                border-top: none;
                border-right: 1px solid var(--border-card);
                padding: 40px 32px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }

            .panel-form {
                order: 2;
                padding: 44px 38px;
            }
        }

        .badge-tag {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--primary);
            background-color: var(--mint-surface);
            border: 1px solid var(--border);
            padding: 4px 10px;
            border-radius: 4px;
            margin-bottom: 18px;
        }

        .info-title {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.3;
            letter-spacing: -0.01em;
            color: var(--primary-hover);
            margin-bottom: 12px;
        }

        .info-desc {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 24px;
            line-height: 1.6;
        }

        .spec-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-bottom: 24px;
        }

        .spec-table tr {
            border-bottom: 1px solid var(--border);
        }

        .spec-table td {
            padding: 10px 0;
            vertical-align: top;
        }

        .spec-label {
            color: var(--text-caption);
            width: 40%;
            font-weight: 600;
        }

        .spec-value {
            color: var(--text-main);
            font-weight: 600;
        }

        .disclosure-card {
            border-left: 3px solid var(--primary);
            background-color: var(--surface);
            border-top: 1px solid var(--border);
            border-right: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            border-radius: 0 6px 6px 0;
            padding: 14px 16px;
            font-size: 12px;
            line-height: 1.5;
            color: var(--text-muted);
        }

        .disclosure-title {
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
            font-size: 11px;
        }

        .form-header {
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 16px;
        }

        .system-tag {
            font-size: 12px;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 6px;
        }

        h1 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: var(--primary-hover);
            margin: 0 0 6px 0;
        }

        .form-subtitle {
            font-size: 13px;
            color: var(--text-muted);
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
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-main);
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
            cursor: pointer;
            border-radius: 6px;
            padding: 10px 20px;
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        .btn-submit:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.3);
        }

        .form-footer-note {
            margin-top: 18px;
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
    <main class="auth-container">
        <div class="auth-grid">
            <aside class="panel-info">
                <div>
                    <span class="badge-tag">Sistem Kasir Material</span>
                    <h2 class="info-title">Terminal Kasir Bahan Bangunan</h2>
                    <p class="info-desc">Sistem operasional modular untuk pencatatan transaksi kasir, kalkulasi muatan armada, dan manajemen persediaan material bangunan.</p>
                    <table class="spec-table" aria-label="Modul operasional sistem">
                        <tbody>
                            <tr>
                                <td class="spec-label">Kasir</td>
                                <td class="spec-value">Penjualan material &amp; POS</td>
                            </tr>
                            <tr>
                                <td class="spec-label">Gudang</td>
                                <td class="spec-value">Stok &amp; penerimaan barang</td>
                            </tr>
                            <tr>
                                <td class="spec-label">Laporan</td>
                                <td class="spec-value">Laba rugi &amp; neraca</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="disclosure-card">
                    <div class="disclosure-title">Prototipe Pengujian</div>
                    <p>Halaman ini beroperasi untuk keperluan uji fungsional prototipe kasir bahan bangunan.</p>
                </div>
            </aside>
            <section class="panel-form" aria-labelledby="login-heading">
                <div class="form-header">
                    <div class="system-tag">Autentikasi Pengguna</div>
                    <h1 id="login-heading">LOGIN KASIR</h1>
                    <p class="form-subtitle">Silakan masukkan identitas akun Anda untuk mengakses register kasir.</p>
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
                <div class="form-footer-note">Sistem internal terproteksi. Pastikan kredensial Anda sesuai hak akses.</div>
            </section>
        </div>
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

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}