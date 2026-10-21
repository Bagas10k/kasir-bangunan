<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class DemoSetupController extends Controller
{
    /**
     * Pastikan environment memenuhi syarat setup demo atau test guard terisolasi.
     */
    protected function ensureDemoAllowed(): void
    {
        $isTesting = app()->environment('testing');

        if ($isTesting) {
            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database");
            if ($connection !== 'sqlite' || $database !== ':memory:') {
                abort(403, 'Akses test ditolak: hanya diizinkan di sqlite :memory:.');
            }
        } else {
            $isDemoEnv = config('app.env') === 'demo';
            $isMysql = config('database.default') === 'mysql';
            $isDemoDb = config('database.connections.mysql.database') === 'kasir_bangunan_demo';

            if (! $isDemoEnv || ! $isMysql || ! $isDemoDb) {
                abort(404);
            }
        }

        if (User::count() > 0) {
            abort(403, 'Akses setup demo ditolak. Akun demo sudah dibuat.');
        }
    }

    /**
     * Tampilkan formulir inisialisasi akun demo pertama.
     */
    public function create()
    {
        $this->ensureDemoAllowed();

        $token = csrf_token();
        $action = route('demo.setup.store');
        $validationMessage = session('errors')?->any()
            ? '<p role="alert" style="color:#9b1c1c;font-weight:700">Periksa nama, alamat email, dan kata sandi (minimal 8 karakter, huruf serta angka).</p>'
            : '';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inisialisasi Akun Demo Toko Bangunan</title>
    <style>
        body { font-family: system-ui, sans-serif; background:#f5f5f4; color:#111; margin:0; min-height:100vh; display:grid; place-items:center; padding:16px; box-sizing:border-box; }
        .card { background:#fff; border:1px solid #444; border-radius:8px; padding:28px; width:min(100%,440px); box-sizing:border-box; }
        h1 { font-size:1.5rem; margin:0 0 12px; } p { line-height:1.5; } label { display:block; font-weight:700; margin:16px 0 6px; }
        input { width:100%; box-sizing:border-box; padding:12px; min-height:44px; font:inherit; border:1px solid #555; border-radius:4px; }
        button { margin-top:20px; width:100%; min-height:46px; background:#111; color:#fff; border:0; border-radius:4px; font:inherit; font-weight:700; cursor:pointer; }
        :focus-visible { outline:3px solid #164f91; outline-offset:3px; }
    </style>
</head>
<body>
    <div class="card">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Setup Akun Demo</h1>
        <p class="text-sm text-gray-600 mb-6">Inisialisasi akun demo pertama toko bangunan. Halaman ini hanya dapat diakses satu kali saat belum ada pengguna.</p>
        {$validationMessage}
        <form method="POST" action="{$action}">
            <input type="hidden" name="_token" value="{$token}">
            
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                <input type="text" id="name" name="name" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Nama akun demo">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Alamat Email</label>
                <input type="email" id="email" name="email" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="akun@example.test">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Kata Sandi</label>
                <input type="password" id="password" name="password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Minimal 8 karakter (huruf & angka)">
                <p class="text-xs text-gray-500 mt-1">Kata sandi wajib minimal 8 karakter, mengandung kombinasi huruf dan angka.</p>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md shadow focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Selesaikan Setup Demo
            </button>
        </form>
    </div>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Simpan akun demo pertama.
     */
    public function store(Request $request)
    {
        $this->ensureDemoAllowed();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ]);

        $connection = DB::connection();
        $isMysql = $connection->getDriverName() === 'mysql';

        if ($isMysql) {
            $lockAcquired = DB::select("SELECT GET_LOCK('demo_setup_lock', 10) as acquired");
            if (empty($lockAcquired) || ! ($lockAcquired[0]->acquired ?? null)) {
                abort(423, 'Sistem sedang memproses inisialisasi lain.');
            }
        }

        try {
            if (User::count() > 0) {
                abort(403, 'Akses setup demo ditolak. Akun demo sudah dibuat.');
            }

            $user = DB::transaction(function () use ($validated) {
                if (User::count() > 0) {
                    abort(403, 'Akses setup demo ditolak. Akun demo sudah dibuat.');
                }

                return User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                ]);
            });

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route('pos.index')->with('status', 'Inisialisasi akun demo berhasil.');
        } finally {
            if ($isMysql) {
                DB::select("SELECT RELEASE_LOCK('demo_setup_lock')");
            }
        }
    }
}
