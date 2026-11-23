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
            $errorMessage = '<div role="alert" class="alert-box">'.$msg.'</div>';
        }

        $escapedAction = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
        $escapedCsrf = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');
        $oldIdentifier = htmlspecialchars((string) old('email', ''), ENT_QUOTES, 'UTF-8');
        $fontBase = htmlspecialchars(rtrim(request()->getBasePath(), '/').'/fonts/kasir/', ENT_QUOTES, 'UTF-8');
        $errorAttr = $errorMessage !== '' ? ' aria-invalid="true" aria-describedby="login-error"' : '';

        $html = '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f2a1d">
    <title>Masuk ke Kasir - Sistem Bahan Bangunan</title>
    <style>
        @font-face{font-family:"Inter Kasir";src:url("'.$fontBase.'inter-latin-400-normal.woff2") format("woff2");font-weight:400;font-display:swap}
        @font-face{font-family:"Inter Kasir";src:url("'.$fontBase.'inter-latin-500-normal.woff2") format("woff2");font-weight:500;font-display:swap}
        @font-face{font-family:"Inter Kasir";src:url("'.$fontBase.'inter-latin-600-normal.woff2") format("woff2");font-weight:600;font-display:swap}
        @font-face{font-family:"Inter Kasir";src:url("'.$fontBase.'inter-latin-700-normal.woff2") format("woff2");font-weight:700;font-display:swap}
        @font-face{font-family:"Bricolage Kasir";src:url("'.$fontBase.'bricolage-grotesque-latin-700-normal.woff2") format("woff2");font-weight:700;font-display:swap}
        :root{--side:#0f2a1d;--green:#14532D;--lime:#C8F26B;--ink:#102A1D;--ink2:#3C4A42;--mute:#5F7468;--line:#DFE6E1;--canvas:#F6F8F7}
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{min-height:100vh;min-height:100dvh;display:grid;place-items:center;padding:24px 16px;font-family:"Inter Kasir",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:var(--ink);background:var(--canvas);-webkit-font-smoothing:antialiased}
        body::before{content:"";position:fixed;inset:0 0 auto;height:42vh;background:var(--side);z-index:-1}
        .login-shell{width:100%;max-width:400px}
        .login-brand{display:flex;align-items:center;gap:12px;margin:0 0 18px 4px;color:#E7F3EC}
        .login-brand strong{display:block;font-size:15px;font-weight:700;color:#fff}
        .login-brand small{display:block;font-size:13px;color:#9FB8A9}
        .login-mark{display:grid;place-items:center;width:44px;height:44px;border-radius:12px;background:#1D5A3A;color:var(--lime);font-size:16px;font-weight:700;letter-spacing:.02em}
        .auth-card{background:#fff;border:1px solid #E4EBE6;border-radius:16px;padding:28px 28px 24px;box-shadow:0 18px 40px -18px rgba(15,42,29,.35);animation:ui-float-in .26s cubic-bezier(.34,1.56,.64,1)}
        @keyframes ui-float-in{from{opacity:0;transform:translateY(-12px) scale(.96)}to{opacity:1;transform:none}}
        h1{font-family:"Bricolage Kasir","Inter Kasir",sans-serif;font-size:26px;line-height:32px;font-weight:700;color:var(--ink);letter-spacing:-.01em}
        .form-subtitle{margin-top:6px;font-size:14px;line-height:20px;color:var(--ink2)}
        .alert-box{margin-top:18px;padding:12px 14px;border-radius:8px;background:#FBE3E0;color:#9B1C1C;font-size:14px;line-height:20px;font-weight:500}
        form{margin-top:20px}
        .form-group+.form-group{margin-top:16px}
        label{display:block;margin-bottom:6px;font-size:14px;line-height:18px;font-weight:600;color:var(--ink)}
        .field{position:relative}
        input[type=text],input[type=password]{width:100%;height:46px;padding:0 14px;border:1px solid var(--line);border-radius:8px;background:#fff;font:inherit;font-size:15px;color:var(--ink);transition:border-color .15s,box-shadow .15s}
        input::placeholder{color:#7A8B81;opacity:1}
        input:hover{border-color:#C5D2CA}
        input:focus{outline:none;border-color:var(--green);box-shadow:0 0 0 3px rgba(20,83,45,.16)}
        input[aria-invalid=true]{border-color:#E7A39B}
        .has-toggle input{padding-right:76px}
        .pw-toggle{position:absolute;right:4px;top:50%;transform:translateY(-50%);min-width:64px;height:38px;padding:0 12px;border:0;border-radius:6px;background:transparent;font:inherit;font-size:13px;font-weight:700;color:var(--green);cursor:pointer}
        .pw-toggle:hover{background:#F1F5F2}
        .remember{display:flex;align-items:center;gap:10px;min-height:44px;margin-top:10px;font-size:14px;font-weight:400;color:var(--ink2);cursor:pointer}
        .remember input{width:20px;height:20px;margin:0;accent-color:var(--green);cursor:pointer}
        .btn-submit{width:100%;height:48px;margin-top:8px;border:0;border-radius:10px;background:var(--green);color:var(--lime);font:inherit;font-size:16px;font-weight:700;cursor:pointer;transition:background-color .15s}
        .btn-submit:hover{background:#0f3f22}
        .btn-submit[aria-busy=true]{opacity:.8;cursor:progress}
        :is(button,input,summary):focus-visible{outline:2px solid var(--green);outline-offset:2px}
        .forgot{margin-top:14px;text-align:center}
        .forgot summary{display:inline-flex;align-items:center;min-height:44px;padding:0 8px;list-style:none;font-size:14px;font-weight:700;color:var(--green);cursor:pointer}
        .forgot summary::-webkit-details-marker{display:none}
        .forgot p{margin:2px auto 0;max-width:300px;padding:10px 12px;border-radius:8px;background:#F1F5F2;font-size:13px;line-height:19px;color:var(--ink2);animation:ui-float-in .26s cubic-bezier(.34,1.56,.64,1)}
        .form-footer-note{margin-top:16px;text-align:center;font-size:13px;color:#5F7468}
        @media (max-width:420px){.auth-card{padding:24px 20px 20px}h1{font-size:24px;line-height:30px}}
        @media (prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}}
    </style>
</head>
<body>
    <div class="login-shell">
        <div class="login-brand"><span class="login-mark" aria-hidden="true">TB</span><span><strong>Kasir Bangunan</strong><small>Terminal Material</small></span></div>
        <main class="auth-card" aria-labelledby="login-heading">
            <h1 id="login-heading">Masuk ke Kasir</h1>
            <p class="form-subtitle">Gunakan akun yang diberikan admin toko.</p>
            '.str_replace('class="alert-box"', 'class="alert-box" id="login-error"', $errorMessage).'
            <form method="POST" action="'.$escapedAction.'" id="login-form">
                <input type="hidden" name="_token" value="'.$escapedCsrf.'">
                <div class="form-group">
                    <label for="email">Nama pengguna atau email</label>
                    <input type="text" id="email" name="email" value="'.$oldIdentifier.'" placeholder="contoh: kasir01 atau kasir@toko.com" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus'.$errorAttr.'>
                </div>
                <div class="form-group">
                    <label for="password">Kata sandi</label>
                    <div class="field has-toggle">
                        <input type="password" id="password" name="password" placeholder="Kata sandi" autocomplete="current-password" required'.$errorAttr.'>
                        <button type="button" class="pw-toggle" aria-controls="password" aria-pressed="false">Lihat</button>
                    </div>
                </div>
                <label class="remember"><input type="checkbox" name="remember" value="1"> Ingat perangkat ini</label>
                <button type="submit" class="btn-submit">Masuk</button>
            </form>
            <details class="forgot">
                <summary>Lupa kata sandi?</summary>
                <p>Hubungi admin toko untuk mengatur ulang kata sandi akun Anda.</p>
            </details>
        </main>
        <p class="form-footer-note">Akses internal toko.</p>
    </div>
    <script>
    (function(){
        var pw=document.getElementById("password"),t=document.querySelector(".pw-toggle"),f=document.getElementById("login-form");
        t.addEventListener("click",function(){var show=pw.type==="password";pw.type=show?"text":"password";t.textContent=show?"Sembunyikan":"Lihat";t.setAttribute("aria-pressed",String(show));pw.focus();});
        f.addEventListener("submit",function(){var b=f.querySelector(".btn-submit");b.setAttribute("aria-busy","true");b.textContent="Memeriksa...";});
    })();
    </script>
</body>
</html>';

        $loading = view('v2.ui-tokens')->render().view('v2.loading-styles')->render()
            .'<div id="login-loading" hidden>'.view('v2.loading-skeleton')->render().'</div>'
            .view('v2.login-loading-script')->render();

        return response(str_replace('</body>', $loading.'</body>', $html));
    }

    public function login(Request $request)
    {
        $input = $request->input('email');
        $identifier = is_string($input) ? Str::lower(trim($input)) : '';
        if (is_string($input)) {
            $request->merge(['email' => $identifier]);
        }
        $throttleKey = Str::transliterate($identifier.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return redirect()->route('login')->withErrors([
                'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ])->withInput($request->except('password'));
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|string|max:255',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->route('login')
                ->withErrors($validator)
                ->withInput($request->except('password'));
        }

        $credentials = $validator->validated();
        if ($credentials['email'] === 'admin') {
            $credentials['email'] = 'admin@kasirbangunan.test';
        } elseif (! filter_var($credentials['email'], FILTER_VALIDATE_EMAIL)) {
            $credentials = ['username' => $credentials['email'], 'password' => $credentials['password']];
        }

        $credentials['is_active'] = true;
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $request->session()->put('v2_auth_stamp', [(int) Auth::id(), (int) Auth::user()->auth_version]);
            RateLimiter::clear($throttleKey);
            $request->session()->forget('url.intended');

            return redirect()->route('pos.index');
        }

        RateLimiter::hit($throttleKey, 60);

        return redirect()->route('login')->withErrors([
            'email' => 'Nama pengguna atau kata sandi salah.',
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
