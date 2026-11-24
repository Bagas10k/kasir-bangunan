# Bukti kode langsung

Dokumen ini adalah tampilan alur dari kode working tree, bukan spesifikasi yang harus diikuti kode. Jika berbeda, kode menang. Setiap langkah mencatat pelaku, pemicu, guard, perubahan status/data, cabang gagal, dan bukti path:baris. Kutipan kode dibaca langsung ketika build; tidak ditulis ulang sebagai pseudocode.

Nomor di kiri adalah baris sumber asli, termasuk baris kosong. Jangan edit keluaran ini.

## 03-akses-master-awal — Akses akun, persiapan master, bagan akun, impor dan saldo awal — jejak sumber V2

### AM01 — Masuk dengan username atau email, dan keluar sesi

Jika input bukan format email dan bukan alias admin, autentikasi diarahkan ke kolom users.username; Auth::attempt mensyaratkan users.is_active. Berhasil meregenerasi sesi, menyimpan pasangan id/auth_version, menghapus intended URL dan menuju pos.index (/). POST /logout (auth) logout, invalidasi sesi dan regenerasi token.

Normalisasi identitas dan rate limiter — `app/Http/Controllers/AuthController.php:151-164`

```text
 151 |         $input = $request->input('email');
 152 |         $identifier = is_string($input) ? Str::lower(trim($input)) : '';
 153 |         if (is_string($input)) {
 154 |             $request->merge(['email' => $identifier]);
 155 |         }
 156 |         $throttleKey = Str::transliterate($identifier.'|'.$request->ip());
 157 | 
 158 |         if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
 159 |             $seconds = RateLimiter::availableIn($throttleKey);
 160 | 
 161 |             return redirect()->route('login')->withErrors([
 162 |                 'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
 163 |             ])->withInput($request->except('password'));
 164 |         }
```

Akun aktif, regenerasi sesi dan cap auth_version — `app/Http/Controllers/AuthController.php:184-195`

```text
 184 |         $credentials['is_active'] = true;
 185 |         if (Auth::attempt($credentials, $request->boolean('remember'))) {
 186 |             $request->session()->regenerate();
 187 |             $request->session()->put('v2_auth_stamp', [(int) Auth::id(), (int) Auth::user()->auth_version]);
 188 |             RateLimiter::clear($throttleKey);
 189 |             $request->session()->forget('url.intended');
 190 | 
 191 |             return redirect()->route('pos.index');
 192 |         }
 193 | 
 194 |         RateLimiter::hit($throttleKey, 60);
 195 | 
```

Logout dan invalidasi — `app/Http/Controllers/AuthController.php:201-208`

```text
 201 |     public function logout(Request $request)
 202 |     {
 203 |         Auth::logout();
 204 |         $request->session()->invalidate();
 205 |         $request->session()->regenerateToken();
 206 | 
 207 |         return redirect()->route('login');
 208 |     }
```

### AM02 — Periksa akun aktif, versi sesi dan peran

Akun hilang/nonaktif atau versi tidak cocok mengakhiri sesi; JSON mendapat 401, HTML menuju login. Cap kosong hanya diterima untuk auth_version=1 atau viaRemember. Operations::role membaca users aktif + v2_user_roles dan hanya menerima admin/cashier; tanpa role sah 403.

Cap versi dan penghentian sesi — `app/Http/Middleware/EnsureActiveAccount.php:13-29`

```text
  13 |         if (Auth::check()) {
  14 |             // Read storage, not a potentially stale guard instance.
  15 |             $user = User::find(Auth::id());
  16 |             $stamp = $request->session()->get('v2_auth_stamp');
  17 |             $current = $user ? [(int) $user->id, (int) $user->auth_version] : null;
  18 |             $missingAllowed = $user && ((int) $user->auth_version === 1 || Auth::viaRemember());
  19 |             if (!$user || !$user->is_active || ($stamp !== null && $stamp !== $current) || ($stamp === null && !$missingAllowed)) {
  20 |                 Auth::logout();
  21 |                 $request->session()->invalidate();
  22 |                 $request->session()->regenerateToken();
  23 |                 if ($request->expectsJson()) return response()->json(['message'=>'Sesi berakhir. Silakan login kembali.'], 401);
  24 |                 return redirect()->route('login')->withErrors(['email'=>'Sesi berakhir. Silakan login kembali.']);
  25 |             }
  26 |             Auth::setUser($user);
  27 |             $request->session()->put('v2_auth_stamp', $current);
  28 |         }
  29 |         return $next($request);
```

Peran aktif dan requireRole — `app/Services/V2/Operations.php:35-50`

```text
  35 |     public function role(int $actor): string
  36 |     {
  37 |         $role = DB::table('v2_user_roles')->join('users', 'users.id', '=', 'v2_user_roles.user_id')->where('v2_user_roles.user_id', $actor)->where('users.is_active', true)->value('v2_user_roles.role');
  38 |         if (! is_string($role) || ! in_array($role, ['admin', 'cashier'], true)) {
  39 |             abort(403);
  40 |         }
  41 | 
  42 |         return $role;
  43 |     }
  44 | 
  45 |     public function requireRole(int $actor, array $roles): void
  46 |     {
  47 |         if (! in_array($this->role($actor), $roles, true)) {
  48 |             abort(403);
  49 |         }
  50 |     }
```

Registrasi middleware web — `bootstrap/app.php:14-16`

```text
  14 |     ->withMiddleware(function (Middleware $middleware): void {
  15 |         $middleware->web(append: [\App\Http\Middleware\EnsureActiveAccount::class, \App\Http\Middleware\V2Cutover::class]);
  16 |     })
```

### AM03 — Batasi arsip legacy saat cutover

Untuk jalur legacy lain yang sudah terautentikasi, hanya admin dengan metode GET lolos; kasir atau metode tulis ditolak 403 oleh server. Root diteruskan; PosController::index mendelegasikan ke workspace bila enabled.

Pengecualian dan gerbang GET admin — `app/Http/Middleware/V2Cutover.php:14-28`

```text
  14 |         if (config('v2.enabled') && $request->is('demo-setup', 'demo-setup/*')) {
  15 |             abort(404);
  16 |         }
  17 |         if (! config('v2.enabled') || $request->is('v2', 'v2/*', 'login', 'logout', 'up')) {
  18 |             return $next($request);
  19 |         }
  20 |         if (! Auth::check()) {
  21 |             return $next($request);
  22 |         }
  23 |         $role = app(Operations::class)->role(Auth::id());
  24 |         if ($request->path() === '/') {
  25 |             return $next($request);
  26 |         }
  27 |         // Legacy remains an admin read-only archive after cutover.
  28 |         abort_unless($role === 'admin' && $request->isMethod('GET'), 403, 'Gunakan alur V2; arsip lama hanya dapat dibaca admin.');
```

Flag konfigurasi bukan fakta runtime — `config/v2.php:1-2`

```text
   1 | <?php
   2 | return ['enabled' => (bool) env('V2_ENABLED', false)];
```

Root memilih workspace kondisional — `app/Http/Controllers/PosController.php:15-19`

```text
  15 |     public function index(Request $request)
  16 |     {
  17 |         if (config('v2.enabled')) {
  18 |             return app(WorkspaceController::class)->index($request);
  19 |         }
```

### AM04 — Baca dan buat akun login (username dan/atau email)

Daftar memakai users LEFT JOIN v2_user_roles tanpa hash dan respons no-store. Pembuatan mengunci role, memeriksa ulang admin aktif serta keunikan username/email lower(trim); membuat users dengan password hash/auth_version=1, role dan audit create secara atomik, respons 201. Akun login bukan v2_parties(kind=employee).

Validasi username/email dan kunci role — `app/Http/Controllers/V2/AccountController.php:66-82`

```text
  66 |         $this->normalizeIdentifiers($request);
  67 |         $validated = $request->validate([
  68 |             'name' => ['required', 'string', 'max:255'],
  69 |             'username' => ['nullable', 'required_without:email', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9][a-z0-9._-]*$/', 'not_in:admin'],
  70 |             'email' => ['nullable', 'required_without:username', 'string', 'max:255', 'email'],
  71 |             'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
  72 |             'role' => ['required', 'string', 'in:admin,cashier'],
  73 |             'is_active' => ['required', 'boolean'],
  74 |         ]);
  75 | 
  76 |         $normalizedEmail = $validated['email'] ?? null;
  77 |         $normalizedUsername = $validated['username'] ?? null;
  78 | 
  79 |         try {
  80 |             return DB::transaction(function () use ($actorId, $validated, $normalizedEmail, $normalizedUsername) {
  81 |                 // Lock ALL v2_user_roles rows in stable user_id order BEFORE fetching actor/target
  82 |                 DB::table('v2_user_roles')->orderBy('user_id')->lockForUpdate()->get();
```

Keunikan identitas dan pembuatan user — `app/Http/Controllers/V2/AccountController.php:91-108`

```text
  91 |                 $this->requireAvailableIdentifiers($normalizedUsername, $normalizedEmail);
  92 | 
  93 |                 $now = now();
  94 |                 $userId = DB::table('users')->insertGetId([
  95 |                     'name' => $validated['name'],
  96 |                     'username' => $normalizedUsername,
  97 |                     'email' => $normalizedEmail,
  98 |                     'password' => Hash::make($validated['password']),
  99 |                     'is_active' => (bool) $validated['is_active'],
 100 |                     'auth_version' => 1,
 101 |                     'remember_token' => Str::random(10),
 102 |                     'created_at' => $now,
 103 |                     'updated_at' => $now,
 104 |                 ]);
 105 | 
 106 |                 DB::table('v2_user_roles')->insert([
 107 |                     'user_id' => $userId,
 108 |                     'role' => $validated['role'],
```

Audit pembuatan akun — `app/Http/Controllers/V2/AccountController.php:113-125`

```text
 113 |                 // Audit log: NO passwords/hash/secrets/PII
 114 |                 DB::table('v2_account_audits')->insert([
 115 |                     'actor_id' => $actorId,
 116 |                     'target_id' => $userId,
 117 |                     'action' => 'create',
 118 |                     'changes' => json_encode([
 119 |                         'changed_fields' => ['name', 'username', 'email', 'role', 'is_active', 'password'],
 120 |                         'role' => $validated['role'],
 121 |                         'is_active' => (bool) $validated['is_active'],
 122 |                     ]),
 123 |                     'created_at' => $now,
 124 |                 ]);
 125 | 
```

### AM05 — Ubah akun tanpa memutus kendali admin

Dilarang nonaktif/demosi diri sendiri dan admin aktif terakhir (422). Shift target berstatus open melarang perubahan role/nonaktif. Username dan email harus unik. users dan role diperbarui, audit update dibuat. Perubahan role, status, password, ATAU username menaikkan auth_version, memutar remember_token serta menghapus sesi DB jika tabel sessions ada.

Larangan self-demotion dan hitung admin aktif — `app/Http/Controllers/V2/AccountController.php:199-216`

```text
 199 |                 // Rule: Forbid self disable / demotion
 200 |                 if ($actorId === $targetId) {
 201 |                     if ($newRole !== 'admin' || ! $newIsActive) {
 202 |                         abort(422, 'Akun sendiri tidak dapat dinonaktifkan atau dilepas dari Admin.');
 203 |                     }
 204 |                 }
 205 | 
 206 |                 // Rule: Always preserve at least 1 active admin
 207 |                 $isTargetActiveAdmin = ($targetRole === 'admin' && (bool) $target->is_active);
 208 |                 $willTargetBeActiveAdmin = ($newRole === 'admin' && $newIsActive);
 209 | 
 210 |                 if ($isTargetActiveAdmin && ! $willTargetBeActiveAdmin) {
 211 |                     $activeAdminCount = DB::table('v2_user_roles')
 212 |                         ->join('users', 'users.id', '=', 'v2_user_roles.user_id')
 213 |                         ->where('v2_user_roles.role', 'admin')
 214 |                         ->where('users.is_active', true)
 215 |                         ->count();
 216 | 
```

Admin terakhir dan shift terbuka — `app/Http/Controllers/V2/AccountController.php:217-234`

```text
 217 |                     if ($activeAdminCount <= 1) {
 218 |                         abort(422, 'Admin aktif terakhir harus dipertahankan.');
 219 |                     }
 220 |                 }
 221 | 
 222 |                 // Rule: Guard downgrade/deactivation while target open v2_shifts to avoid stranded shift
 223 |                 $hasOpenShift = DB::table('v2_shifts')
 224 |                     ->where('user_id', $targetId)
 225 |                     ->where('status', 'open')
 226 |                     ->exists();
 227 | 
 228 |                 if ($hasOpenShift) {
 229 |                     $isRoleChange = ($targetRole !== $newRole);
 230 |                     $isDeactivation = ((bool) $target->is_active && ! $newIsActive);
 231 |                     if ($isRoleChange || $isDeactivation) {
 232 |                         abort(422, 'Tutup shift akun ini dahulu sebelum mengganti role atau menonaktifkannya.');
 233 |                     }
 234 |                 }
```

Invalidasi versi kondisional dan update — `app/Http/Controllers/V2/AccountController.php:276-291`

```text
 276 |                 // On change role/status/password increment auth_version, rotate remember_token, delete sessions
 277 |                 if ($roleChanged || $statusChanged || $passwordChanged || $usernameChanged) {
 278 |                     $userUpdates['auth_version'] = (int) $target->auth_version + 1;
 279 |                     $userUpdates['remember_token'] = Str::random(60);
 280 | 
 281 |                     if (Schema::hasTable('sessions')) {
 282 |                         DB::table('sessions')->where('user_id', $targetId)->delete();
 283 |                     }
 284 |                 }
 285 | 
 286 |                 DB::table('users')->where('id', $targetId)->update($userUpdates);
 287 | 
 288 |                 DB::table('v2_user_roles')->updateOrInsert(
 289 |                     ['user_id' => $targetId],
 290 |                     ['role' => $newRole, 'updated_at' => $now, 'created_at' => $now]
 291 |                 );
```

### AM06 — Baca dan buat master dasar

GET membaca maksimal 100 record terbaru per kind, produk disertai supplier. POST atomik membuat produk stok 0, kendaraan status active atau pihak is_active=true; produk melalui resolusi satuan/kategori dan supplier. Customer price_tier hanya retail/contractor; contractor_price positif atau null. Employee hanya master pihak, tidak membuat users/role atau akses login.

Admin, kind dan keunikan — `app/Services/V2/MasterData.php:96-111`

```text
  96 |     public function store(int $actor, string $kind, array $data): int
  97 |     {
  98 |         $this->requireAdmin($actor);
  99 |         if (! in_array($kind, self::KINDS, true)) {
 100 |             throw new RuntimeException("Kind {$kind} tidak valid.");
 101 |         }
 102 | 
 103 |         return DB::transaction(function () use ($kind, $data) {
 104 |             $row = $this->validateRow($kind, $data);
 105 |             [$table, $keyColumn, $keyLabel] = $this->keySpec($kind);
 106 |             $keyValue = $row[$keyColumn];
 107 | 
 108 |             if (DB::table($table)->where($keyColumn, $keyValue)->lockForUpdate()->exists()) {
 109 |                 throw new RuntimeException("{$keyLabel} {$keyValue} sudah terdaftar.");
 110 |             }
 111 | 
```

Efek create master tanpa stok awal — `app/Services/V2/MasterData.php:112-129`

```text
 112 |             $now = now();
 113 |             if ($kind === 'product') {
 114 |                 $row = app(MasterRelations::class)->productAttributes($data, $row);
 115 |                 $payload = $row + ['stock_quantity' => '0.0000', 'is_active' => true]; // zero product stock
 116 |             } elseif ($kind === 'vehicle') {
 117 |                 $payload = $row + ['status' => 'active'];
 118 |             } else {
 119 |                 $payload = $row + ['kind' => $kind, 'is_active' => true];
 120 |             }
 121 |             $payload['created_at'] = $now;
 122 |             $payload['updated_at'] = $now;
 123 | 
 124 |             $id = DB::table($table)->insertGetId($payload);
 125 |             if ($kind === 'product') {
 126 |                 app(MasterRelations::class)->syncProductSuppliers($id, $data);
 127 |             }
 128 | 
 129 |             return $id;
```

### AM07 — Ubah, arsip, atau aktifkan kembali master tanpa hapus riwayat

PUT tidak mengganti kind/stok/status arsip; HPP produk tidak boleh berubah bila stok nonzero atau ada stock_movements. PATCH arsip menonaktifkan (kendaraan status inactive, lainnya is_active=false) tanpa hard delete; PATCH activate memulihkan kembali dari arsip (active/is_active=true) dengan riwayat utuh.

HPP terkunci dan pembaruan — `app/Services/V2/MasterData.php:176-188`

```text
 176 |             if ($kind === 'product'
 177 |                 && $row['cost_price'] !== $this->decimal($current->cost_price ?? '0', 'cost_price', 4)
 178 |                 && $this->costLockedIds([$id]) !== []) {
 179 |                 throw new RuntimeException('HPP tidak dapat diubah: produk sudah punya stok atau riwayat pergerakan. Ubah HPP lewat penerimaan/saldo awal.');
 180 |             }
 181 | 
 182 |             if ($kind === 'product') {
 183 |                 $row = app(MasterRelations::class)->productAttributes($data, $row, $current);
 184 |                 app(MasterRelations::class)->syncProductSuppliers($id, $data);
 185 |             }
 186 |             $row['updated_at'] = now();
 187 |             DB::table($table)->where('id', $id)->update($row);
 188 |         });
```

Arsip bukan delete — `app/Services/V2/MasterData.php:192-208`

```text
 192 |     public function archive(int $actor, string $kind, int $id): void
 193 |     {
 194 |         $this->requireAdmin($actor);
 195 |         $this->assertKind($kind);
 196 | 
 197 |         DB::transaction(function () use ($kind, $id): void {
 198 |             [$table] = $this->keySpec($kind);
 199 |             if (! $this->rowQuery($kind, $id)->lockForUpdate()->exists()) {
 200 |                 throw new RuntimeException('Data tidak ditemukan.');
 201 |             }
 202 | 
 203 |             $payload = $kind === 'vehicle'
 204 |                 ? ['status' => 'inactive', 'updated_at' => now()]
 205 |                 : ['is_active' => false, 'updated_at' => now()];
 206 |             DB::table($table)->where('id', $id)->update($payload);
 207 |         });
 208 |     }
```

Aktivasi kembali dari arsip — `app/Services/V2/MasterData.php:211-227`

```text
 211 |     public function activate(int $actor, string $kind, int $id): void
 212 |     {
 213 |         $this->requireAdmin($actor);
 214 |         $this->assertKind($kind);
 215 | 
 216 |         DB::transaction(function () use ($kind, $id): void {
 217 |             [$table] = $this->keySpec($kind);
 218 |             if (! $this->rowQuery($kind, $id)->lockForUpdate()->exists()) {
 219 |                 throw new RuntimeException('Data tidak ditemukan.');
 220 |             }
 221 | 
 222 |             $payload = $kind === 'vehicle'
 223 |                 ? ['status' => 'active', 'updated_at' => now()]
 224 |                 : ['is_active' => true, 'updated_at' => now()];
 225 |             DB::table($table)->where('id', $id)->update($payload);
 226 |         });
 227 |     }
```

### AM08 — Kelola satuan dan kategori (termasuk aktivasi ulang)

Membuat product_options aktif; rename memperbarui teks unit/category pada products yang terhubung ID. Arsip menyetel is_active=false tanpa menghapus relasi lama; activate memulihkan is_active=true. Pilihan produk baru wajib opsi aktif, sedangkan tautan lama tetap sah walau opsi diarsip.

Create relasi admin — `app/Services/V2/MasterRelations.php:31-44`

```text
  31 |     public function store(int $actor, string $kind, array $data): int
  32 |     {
  33 |         app(Operations::class)->requireRole($actor, ['admin']);
  34 |         $this->assertKind($kind);
  35 | 
  36 |         return DB::transaction(function () use ($kind, $data): int {
  37 |             $row = $this->attributes($kind, $data);
  38 |             $this->assertUnique($kind, $row);
  39 | 
  40 |             return DB::table($kind === 'driver' ? 'v2_drivers' : 'product_options')->insertGetId($row + [
  41 |                 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
  42 |             ]);
  43 |         });
  44 |     }
```

Rename cascade dan arsip — `app/Services/V2/MasterRelations.php:63-80`

```text
  63 |             if ($kind !== 'driver') {
  64 |                 DB::table('products')->where($kind.'_id', $id)->update([$kind => $row['name'], 'updated_at' => now()]);
  65 |             }
  66 |         });
  67 |     }
  68 | 
  69 |     public function archive(int $actor, string $kind, int $id): void
  70 |     {
  71 |         app(Operations::class)->requireRole($actor, ['admin']);
  72 |         $this->assertKind($kind);
  73 | 
  74 |         DB::transaction(function () use ($kind, $id): void {
  75 |             if (! $this->rowQuery($kind, $id)->lockForUpdate()->exists()) {
  76 |                 throw new RuntimeException('Data tidak ditemukan.');
  77 |             }
  78 |             $this->rowQuery($kind, $id)->update(['is_active' => false, 'updated_at' => now()]);
  79 |         });
  80 |     }
```

Aktivasi ulang relasi — `app/Services/V2/MasterRelations.php:82-93`

```text
  82 |     public function activate(int $actor, string $kind, int $id): void
  83 |     {
  84 |         app(Operations::class)->requireRole($actor, ['admin']);
  85 |         $this->assertKind($kind);
  86 | 
  87 |         DB::transaction(function () use ($kind, $id): void {
  88 |             if (! $this->rowQuery($kind, $id)->lockForUpdate()->exists()) {
  89 |                 throw new RuntimeException('Data tidak ditemukan.');
  90 |             }
  91 |             $this->rowQuery($kind, $id)->update(['is_active' => true, 'updated_at' => now()]);
  92 |         });
  93 |     }
```

### AM09 — Kelola sopir terpisah dari akun login

Create/PUT menyimpan v2_drivers, internal memakai nama employee; saat PUT menuju external employee_id dikosongkan. PATCH menonaktifkan atau mengaktifkan kembali sopir tanpa menghapus. Driver resolver menolak sopir nonaktif/pegawai internal nonaktif. Tidak ada pembuatan users/role pada alur ini.

Jenis sopir dan employee — `app/Services/V2/MasterRelations.php:192-209`

```text
 192 |     private function attributes(string $kind, array $data): array
 193 |     {
 194 |         if ($kind !== 'driver') {
 195 |             return ['type' => $kind, 'name' => $this->text($data['name'] ?? '', 'name', $kind === 'unit' ? 32 : 50)];
 196 |         }
 197 |         $driverKind = $data['kind'] ?? null;
 198 |         if (! in_array($driverKind, ['internal', 'external'], true)) {
 199 |             throw new RuntimeException('Jenis sopir harus internal atau external.');
 200 |         }
 201 |         $employeeId = $this->nullableId($data['employee_id'] ?? null, 'employee_id');
 202 |         if ($driverKind === 'external' && $employeeId !== null) {
 203 |             throw new RuntimeException('Sopir eksternal tidak boleh terhubung ke pegawai.');
 204 |         }
 205 |         $name = $driverKind === 'internal'
 206 |             ? $this->employee($employeeId ?? 0)->name
 207 |             : $this->text($data['name'] ?? '', 'name', 255);
 208 | 
 209 |         return [
```

Employee aktif dan keunikan sopir — `app/Services/V2/MasterRelations.php:217-234`

```text
 217 |     private function employee(int $id): object
 218 |     {
 219 |         $employee = DB::table('v2_parties')->where('id', $id)->where('kind', 'employee')->where('is_active', true)->lockForUpdate()->first();
 220 |         if (! $employee) {
 221 |             throw new RuntimeException('Pegawai sopir tidak aktif atau tidak ditemukan.');
 222 |         }
 223 | 
 224 |         return $employee;
 225 |     }
 226 | 
 227 |     private function assertUnique(string $kind, array $row, ?int $except = null): void
 228 |     {
 229 |         $query = DB::table($kind === 'driver' ? 'v2_drivers' : 'product_options');
 230 |         if ($except !== null) {
 231 |             $query->where('id', '<>', $except);
 232 |         }
 233 |         if ($kind === 'driver') {
 234 |             if ((clone $query)->where('code', $row['code'])->exists()) {
```

Resolver sopir memeriksa status — `app/Services/V2/MasterRelations.php:96-110`

```text
  96 |     public function driver(int $id): object
  97 |     {
  98 |         $this->requireTransaction();
  99 |         $driver = DB::table('v2_drivers')->where('id', $id)->where('is_active', true)->lockForUpdate()->first();
 100 |         if (! $driver) {
 101 |             throw new RuntimeException('Sopir tidak aktif atau tidak ditemukan.');
 102 |         }
 103 |         if ($driver->kind === 'internal') {
 104 |             $driver->name = $this->employee((int) $driver->employee_id)->name;
 105 |         } elseif ($driver->kind !== 'external' || $driver->employee_id !== null) {
 106 |             throw new RuntimeException('Relasi sopir tidak valid.');
 107 |         }
 108 | 
 109 |         return $driver;
 110 |     }
```

### AM10 — Validasi tautan produk ke opsi dan supplier

Teks opsi baru dapat membuat product_options saat write saja; pilihan baru harus aktif, tautan opsi lama boleh tetap walau arsip. Supplier baru harus kind supplier aktif; supplier terarsip yang sudah tertaut boleh dipertahankan. Primary harus anggota daftar. Kesalahan membatalkan transaksi master.

Resolusi opsi aktif atau tautan lama — `app/Services/V2/MasterRelations.php:118-135`

```text
 118 |             if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
 119 |                 $id = $this->id($data[$field], $field);
 120 |                 $option = DB::table('product_options')->where('id', $id)->where('type', $kind)->lockForUpdate()->first();
 121 |             } else {
 122 |                 $name = $this->text($row[$kind], $kind, $kind === 'unit' ? 32 : 50);
 123 |                 $option = DB::table('product_options')->where('type', $kind)->where('name', $name)->lockForUpdate()->first();
 124 |                 if (! $option) {
 125 |                     DB::table('product_options')->insertOrIgnore([
 126 |                         'type' => $kind, 'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
 127 |                     ]);
 128 |                     $option = DB::table('product_options')->where('type', $kind)->where('name', $name)->lockForUpdate()->first();
 129 |                 }
 130 |             }
 131 |             // Master yang sudah tertaut ke produk ini tetap sah walau diarsip; pilihan baru wajib aktif.
 132 |             $alreadyLinked = $option && $current !== null && (int) ($current->{$field} ?? 0) === (int) $option->id;
 133 |             if (! $option || (! $option->is_active && ! $alreadyLinked)) {
 134 |                 throw new RuntimeException("Master {$kind} tidak aktif atau tidak ditemukan.");
 135 |             }
```

Primary dan supplier aktif — `app/Services/V2/MasterRelations.php:159-173`

```text
 159 |         $primary = array_key_exists('primary_supplier_id', $data)
 160 |             ? $this->nullableId($data['primary_supplier_id'], 'primary_supplier_id')
 161 |             : ($oldPrimary && in_array((int) $oldPrimary->supplier_id, $ids, true) ? (int) $oldPrimary->supplier_id : null);
 162 |         if ($primary !== null && ! in_array($primary, $ids, true)) {
 163 |             throw new RuntimeException('Supplier utama harus termasuk daftar supplier produk.');
 164 |         }
 165 |         $validateIds = $replace ? $ids : ($primary === null ? [] : [$primary]);
 166 |         $suppliers = DB::table('v2_parties')->whereIn('id', $validateIds)->where('kind', 'supplier')
 167 |             ->orderBy('id')->lockForUpdate()->get(['id', 'is_active']);
 168 |         // Link yang sudah ada ke supplier terarsip dipertahankan; link baru wajib ke supplier aktif.
 169 |         $linkedIds = $existing->pluck('supplier_id')->map(fn ($id): int => (int) $id)->all();
 170 |         $usable = $suppliers->every(fn (object $supplier): bool => (bool) $supplier->is_active || in_array((int) $supplier->id, $linkedIds, true));
 171 |         if ($suppliers->pluck('id')->map(fn ($id): int => (int) $id)->all() !== $validateIds || ! $usable) {
 172 |             throw new RuntimeException('Supplier tidak aktif atau tidak ditemukan.');
 173 |         }
```

Penggantian tautan versus primary saja — `app/Services/V2/MasterRelations.php:175-190`

```text
 175 |         if ($replace) {
 176 |             DB::table('v2_product_suppliers')->where('product_id', $productId)->delete();
 177 |             foreach ($ids as $supplierId) {
 178 |                 DB::table('v2_product_suppliers')->insert([
 179 |                     'product_id' => $productId, 'supplier_id' => $supplierId, 'is_primary' => $supplierId === $primary,
 180 |                     'created_at' => now(), 'updated_at' => now(),
 181 |                 ]);
 182 |             }
 183 |         } else {
 184 |             DB::table('v2_product_suppliers')->where('product_id', $productId)->update(['is_primary' => false, 'updated_at' => now()]);
 185 |             if ($primary !== null) {
 186 |                 DB::table('v2_product_suppliers')->where('product_id', $productId)->where('supplier_id', $primary)
 187 |                     ->update(['is_primary' => true, 'updated_at' => now()]);
 188 |             }
 189 |         }
 190 |     }
```

### AM11 — Cari atau buat pelanggan dari kasir

Search di bawah 2 karakter kosong; hanya customer aktif, nama mengandung term atau kode berawalan term, default maksimal 8. Resolve memakai kecocokan nama case-insensitive: customer aktif dikembalikan, terarsip ditolak 422; bila belum ada dibuat kode CUS berurutan aktif. Tidak menyentuh uang/stok/jurnal.

Rute pencarian dan resolve serta izin — `app/Http/Controllers/V2/WorkspaceController.php:439-453`

```text
 439 |     public function searchCustomers(Request $request): JsonResponse
 440 |     {
 441 |         $this->operations->requireRole($request->user()->id, ['admin', 'cashier']);
 442 |         $data = $request->validate(['q' => ['required', 'string', 'max:255']]);
 443 | 
 444 |         return response()->json(['results' => app(CustomerLookup::class)->search($data['q'])]);
 445 |     }
 446 | 
 447 |     /** Memakai pelanggan yang sudah ada, atau membuatnya bila nama itu belum terdaftar. */
 448 |     public function resolveCustomer(Request $request): JsonResponse
 449 |     {
 450 |         $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
 451 | 
 452 |         return $this->run(fn () => app(CustomerLookup::class)->resolve($request->user()->id, $data['name']));
 453 |     }
```

Normalisasi dan pencarian terkunci — `app/Services/V2/CustomerLookup.php:48-64`

```text
  48 |     public function resolve(int $actor, string $name): array
  49 |     {
  50 |         $this->operations->requireRole($actor, ['admin', 'cashier']);
  51 |         $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
  52 |         if (mb_strlen($name) < self::MIN_LENGTH) {
  53 |             throw new RuntimeException('Nama pelanggan minimal '.self::MIN_LENGTH.' huruf.');
  54 |         }
  55 |         if (mb_strlen($name) > self::MAX_NAME) {
  56 |             throw new RuntimeException('Nama pelanggan maksimal '.self::MAX_NAME.' huruf.');
  57 |         }
  58 | 
  59 |         return DB::transaction(function () use ($name) {
  60 |             $existing = DB::table('v2_parties')
  61 |                 ->where('kind', 'customer')
  62 |                 ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
  63 |                 ->lockForUpdate()
  64 |                 ->first(['id', 'code', 'name', 'is_active', 'price_tier']);
```

Tolak arsip atau buat pelanggan — `app/Services/V2/CustomerLookup.php:66-80`

```text
  66 |             if ($existing) {
  67 |                 if (! $existing->is_active) {
  68 |                     throw new RuntimeException('Pelanggan "'.$existing->name.'" sedang diarsipkan. Minta admin mengaktifkannya.');
  69 |                 }
  70 | 
  71 |                 return ['id' => (int) $existing->id, 'code' => (string) $existing->code, 'name' => (string) $existing->name, 'price_tier' => (string) ($existing->price_tier ?: 'retail'), 'created' => false];
  72 |             }
  73 | 
  74 |             $code = $this->nextCode();
  75 |             $id = DB::table('v2_parties')->insertGetId([
  76 |                 'kind' => 'customer', 'code' => $code, 'name' => $name, 'is_active' => true,
  77 |                 'created_at' => now(), 'updated_at' => now(),
  78 |             ]);
  79 | 
  80 |             return ['id' => (int) $id, 'code' => $code, 'name' => $name, 'price_tier' => 'retail', 'created' => true];
```

### AM12 — Unduh template master XLSX

Menghasilkan XLSX header sesuai columns(kind), sebagai unduhan private/no-store tanpa mutasi master. Template produk berisi sku,name,unit,category,cost_price,selling_price,min_stock; tidak menyediakan stok awal atau tautan supplier.

Kolom template per kind — `app/Services/V2/MasterData.php:51-59`

```text
  51 |     public function columns(string $kind): array
  52 |     {
  53 |         return match ($kind) {
  54 |             'product' => ['sku', 'name', 'unit', 'category', 'cost_price', 'selling_price', 'min_stock'],
  55 |             'customer', 'supplier', 'employee' => ['code', 'name', 'notes'],
  56 |             'vehicle' => ['license_plate', 'vehicle_type'],
  57 |             default => throw new RuntimeException("Kind {$kind} tidak valid."),
  58 |         };
  59 |     }
```

Gerbang dan pembuatan template — `app/Services/V2/MasterData.php:374-381`

```text
 374 |     public function template(int $actor, string $kind): string
 375 |     {
 376 |         $this->requireAdmin($actor);
 377 |         $this->assertXlsxKind($kind);
 378 |         $cols = $this->columns($kind);
 379 | 
 380 |         return $this->buildMinimalXlsx($cols);
 381 |     }
```

Larangan XLSX relation kind — `app/Services/V2/MasterData.php:521-527`

```text
 521 |     private function assertXlsxKind(string $kind): void
 522 |     {
 523 |         if (in_array($kind, self::RELATION_KINDS, true)) {
 524 |             throw new RuntimeException("XLSX tidak tersedia untuk master {$kind}.");
 525 |         }
 526 |         $this->assertKind($kind);
 527 |     }
```

### AM13 — Pratinjau impor dan simpan batch tervalidasi

Validasi kolom/angka dan duplikasi key dalam file maupun DB. Gagal memberi errors dan batch_id=null tanpa menulis master; berhasil menyimpan v2_import_batches status preview, rows normalisasi dan actor_id, lalu mengembalikan batch_id/count serta maksimal 100 baris.

Upload dan admin — `app/Http/Controllers/V2/WorkspaceController.php:621-627`

```text
 621 |     public function importPreview(Request $request, string $kind): JsonResponse
 622 |     {
 623 |         $this->operations->requireRole($request->user()->id, ['admin']);
 624 |         $request->validate(['file' => ['required', 'file', 'max:2048']]);
 625 | 
 626 |         return $this->run(fn () => app(MasterData::class)->preview($request->user()->id, $kind, $request->file('file')->getRealPath()));
 627 |     }
```

Bentrok DB menolak batch — `app/Services/V2/MasterData.php:434-449`

```text
 434 |         // Unik terhadap data yang sudah ada: batch tidak boleh dibuat bila ada bentrok.
 435 |         if (empty($errors)) {
 436 |             $existing = $this->existingKeys($kind, array_keys($uniqueKeys));
 437 |             if (! empty($existing)) {
 438 |                 $errors[] = 'Data sudah terdaftar di database: '.implode(', ', $existing).'.';
 439 |             }
 440 |         }
 441 | 
 442 |         if (! empty($errors)) {
 443 |             return [
 444 |                 'batch_id' => null,
 445 |                 'count' => $rowCount,
 446 |                 'rows' => array_slice($parsedRows, 0, 100),
 447 |                 'errors' => $errors,
 448 |             ];
 449 |         }
```

Persistensi batch preview — `app/Services/V2/MasterData.php:451-468`

```text
 451 |         // Simpan preview batch ke database
 452 |         $batchId = DB::table('v2_import_batches')->insertGetId([
 453 |             'actor_id' => $actor,
 454 |             'kind' => $kind,
 455 |             'status' => 'preview',
 456 |             'row_count' => count($validatedRecords),
 457 |             'rows' => json_encode($validatedRecords, JSON_THROW_ON_ERROR),
 458 |             'committed_at' => null,
 459 |             'created_at' => now(),
 460 |             'updated_at' => now(),
 461 |         ]);
 462 | 
 463 |         return [
 464 |             'batch_id' => $batchId,
 465 |             'count' => count($validatedRecords),
 466 |             'rows' => array_slice($validatedRecords, 0, 100),
 467 |             'errors' => [],
 468 |         ];
```

### AM14 — Commit batch impor milik sendiri

Batch committed dikembalikan idempoten tanpa insert ulang. Batch belum committed dibaca rows/kind lalu tiap baris masuk MasterData::store untuk revalidasi aturan terbaru. Seluruh insert dan perubahan status committed satu transaksi; produk impor tetap stok 0, tanpa jurnal/faktur awal.

Kunci batch dan kepemilikan — `app/Services/V2/MasterData.php:471-488`

```text
 471 |     public function commit(int $actor, int $batchId): array
 472 |     {
 473 |         $this->requireAdmin($actor);
 474 | 
 475 |         return DB::transaction(function () use ($actor, $batchId) {
 476 |             $batch = DB::table('v2_import_batches')->where('id', $batchId)->lockForUpdate()->first();
 477 |             if (! $batch) {
 478 |                 throw new RuntimeException('Batch import tidak ditemukan.');
 479 |             }
 480 |             if ((int) $batch->actor_id !== $actor) {
 481 |                 abort(403);
 482 |             }
 483 | 
 484 |             // Idempotent check
 485 |             if ($batch->status === 'committed') {
 486 |                 return [
 487 |                     'batch_id' => $batch->id,
 488 |                     'kind' => $batch->kind,
```

Commit melalui store secara atomik — `app/Services/V2/MasterData.php:499-517`

```text
 499 |             // Revalidasi + insert per baris lewat satu jalur (store): aturan sama dengan form manual,
 500 |             // dan kegagalan satu baris membatalkan seluruh transaksi komit.
 501 |             foreach ($rows as $row) {
 502 |                 $this->store($actor, $kind, $row);
 503 |             }
 504 | 
 505 |             DB::table('v2_import_batches')->where('id', $batchId)->update([
 506 |                 'status' => 'committed',
 507 |                 'committed_at' => now(),
 508 |                 'updated_at' => now(),
 509 |             ]);
 510 | 
 511 |             return [
 512 |                 'batch_id' => $batch->id,
 513 |                 'kind' => $kind,
 514 |                 'status' => 'committed',
 515 |                 'inserted_count' => count($rows),
 516 |                 'already_committed' => false,
 517 |             ];
```

Aturan insert produk stok nol — `app/Services/V2/MasterData.php:112-127`

```text
 112 |             $now = now();
 113 |             if ($kind === 'product') {
 114 |                 $row = app(MasterRelations::class)->productAttributes($data, $row);
 115 |                 $payload = $row + ['stock_quantity' => '0.0000', 'is_active' => true]; // zero product stock
 116 |             } elseif ($kind === 'vehicle') {
 117 |                 $payload = $row + ['status' => 'active'];
 118 |             } else {
 119 |                 $payload = $row + ['kind' => $kind, 'is_active' => true];
 120 |             }
 121 |             $payload['created_at'] = $now;
 122 |             $payload['updated_at'] = $now;
 123 | 
 124 |             $id = DB::table($table)->insertGetId($payload);
 125 |             if ($kind === 'product') {
 126 |                 app(MasterRelations::class)->syncProductSuppliers($id, $data);
 127 |             }
```

### AM15 — Pratinjau saldo awal sistem bersih

Tanpa mutasi DB: normalisasi desimal, tanggal valid, referensi max50, kas/bank/qty/HPP nonnegatif; produk harus ada dan tidak duplikat. Piutang butuh customer aktif, utang supplier aktif, nomor faktur unik, amount positif dan due_date valid. Ringkasan serta rancangan jurnal mengimbangi selisih aset-utang ke ekuitas 3101.

Preview tanpa write — `app/Services/V2/OpeningBalances.php:60-74`

```text
  60 |     public function preview(int $actor, array $data): array
  61 |     {
  62 |         $this->requireAdmin($actor);
  63 |         $this->assertSystemCanAcceptOpening();
  64 | 
  65 |         $normalized = $this->validateAndNormalize($data);
  66 | 
  67 |         return [
  68 |             'status' => 'preview',
  69 |             'summary' => $normalized['summary'],
  70 |             'journal_lines' => $normalized['journal_lines'],
  71 |             'inventory' => $normalized['inventory'],
  72 |             'receivables' => $normalized['receivables'],
  73 |             'payables' => $normalized['payables'],
  74 |         ];
```

Batch dan jurnal harus kosong — `app/Services/V2/OpeningBalances.php:261-278`

```text
 261 |     private function assertSystemCanAcceptOpening(): void
 262 |     {
 263 |         // 1. Cek apakah v2_opening_batches sudah ada
 264 |         if (DB::table('v2_opening_batches')->exists()) {
 265 |             throw ValidationException::withMessages([
 266 |                 'opening_balance' => 'Saldo awal V2 sudah pernah diposting dan bersifat sekali pakai (singleton).',
 267 |             ]);
 268 |         }
 269 | 
 270 |         // 2. Cek apakah ada jurnal di journal_entries
 271 |         $journalCount = DB::table('journal_entries')->count();
 272 |         if ($journalCount > 0) {
 273 |             throw ValidationException::withMessages([
 274 |                 'journal' => 'Tidak dapat memposting saldo awal: jurnal transaksi keuangan sudah ada dalam sistem.',
 275 |             ]);
 276 |         }
 277 | 
 278 |         // 3. Cek apakah ada stok produk yang bernilai bukan nol
```

Stok dan faktur harus kosong — `app/Services/V2/OpeningBalances.php:279-291`

```text
 279 |         $hasNonZeroStock = DB::table('products')->where('stock_quantity', '!=', 0)->exists();
 280 |         if ($hasNonZeroStock) {
 281 |             throw ValidationException::withMessages([
 282 |                 'inventory' => 'Tidak dapat memposting saldo awal: stok produk telah terisi sebelum saldo awal dibuka.',
 283 |             ]);
 284 |         }
 285 | 
 286 |         // 4. Cek apakah sudah ada faktur V2
 287 |         if (DB::table('v2_invoices')->exists()) {
 288 |             throw ValidationException::withMessages([
 289 |                 'invoices' => 'Tidak dapat memposting saldo awal: data faktur V2 sudah ada.',
 290 |             ]);
 291 |         }
```

### AM16 — Posting saldo awal sekali, atomik dan immutable

Jika batch sudah ada, key DAN hash payload sama mengembalikan idempotent_ok; selain itu ditolak sebagai immutable. Posting membuat v2_opening_batches, jurnal seimbang melalui Ledger, memperbarui stok/HPP dan stock_movements, membuat faktur receivable/payable open paid=0 tanpa sale/receipt palsu. Tidak tersedia edit/reset saldo awal di routes/v2.php.

Key/hash dan replay identik — `app/Services/V2/OpeningBalances.php:84-101`

```text
  84 |     public function post(int $actor, array $data): array
  85 |     {
  86 |         $this->requireAdmin($actor);
  87 | 
  88 |         $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
  89 |         if ($idempotencyKey === '') {
  90 |             throw ValidationException::withMessages(['idempotency_key' => 'Idempotency key wajib diisi.']);
  91 |         }
  92 | 
  93 |         $requestHash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
  94 | 
  95 |         // Cek batch pembukaan yang sudah ada
  96 |         $existing = DB::table('v2_opening_batches')->where('singleton_key', self::SINGLETON_KEY)->first();
  97 |         if ($existing) {
  98 |             if ($existing->idempotency_key === $idempotencyKey && $existing->request_hash === $requestHash) {
  99 |                 return [
 100 |                     'status' => 'idempotent_ok',
 101 |                     'batch_id' => $existing->id,
```

Stok dan mutasi pembukaan — `app/Services/V2/OpeningBalances.php:191-208`

```text
 191 |                 // Update produk secara atomik
 192 |                 DB::table('products')->where('id', $productId)->update([
 193 |                     'stock_quantity' => $qty,
 194 |                     'cost_price' => $cost,
 195 |                     'updated_at' => now(),
 196 |                 ]);
 197 | 
 198 |                 // Catat mutasi stok pembukaan
 199 |                 DB::table('stock_movements')->insert([
 200 |                     'product_id' => $productId,
 201 |                     'reference_type' => 'v2_opening_balance',
 202 |                     'reference_id' => $batchId,
 203 |                     'quantity_delta' => $qty,
 204 |                     'balance_after' => $qty,
 205 |                     'notes' => 'Saldo awal stok V2: ' . $normalized['reference'],
 206 |                     'created_at' => now(),
 207 |                     'updated_at' => now(),
 208 |                 ]);
```

Faktur piutang tanpa penjualan palsu — `app/Services/V2/OpeningBalances.php:211-225`

```text
 211 |             // 4. Buat Faktur Piutang (Receivables) tanpa transaksi penjualan palsu
 212 |             foreach ($normalized['receivables'] as $ar) {
 213 |                 DB::table('v2_invoices')->insert([
 214 |                     'direction' => 'receivable',
 215 |                     'party_id' => $ar['party_id'],
 216 |                     'sale_id' => null,
 217 |                     'receipt_id' => null,
 218 |                     'number' => $ar['number'],
 219 |                     'amount' => $ar['amount'],
 220 |                     'paid' => '0.00',
 221 |                     'due_date' => $ar['due_date'],
 222 |                     'status' => 'open',
 223 |                     'created_at' => now(),
 224 |                     'updated_at' => now(),
 225 |                 ]);
```

### AM17 — Kelola Bagan Akun (COA) dan tandai Rekening Bank aktif

Menambah, memperbarui, atau mengarsipkan akun pada chart_of_accounts tanpa hard delete; akun yang sudah dipakai jurnal tidak boleh diubah kode/jenis/saldo normalnya dan akun inti sistem tidak boleh diubah kodenya atau diarsip. Setiap akun aktif bertanda is_bank_account=true (bersama 1102) dibaca terpusat lewat BankAccounts dan otomatis tersedia sebagai pilihan Rekening Bank pada Kasir, Tagihan, Verifikasi, Pengeluaran, Perjalanan, Ongkir Penerimaan, Penyesuaian/Pindah Dana, Laporan Harian, dan Arus Kas.

Simpan akun COA beserta penanda Rekening Bank — `app/Http/Controllers/ChartOfAccountController.php:160-179`

```text
 160 |         $isBank = array_key_exists('is_bank_account', $validated)
 161 |             ? (bool) $validated['is_bank_account']
 162 |             : ($code === BankAccounts::DEFAULT_BANK);
 163 |         if ($bankError = $this->bankFlagError($code, $validated['account_type'], $validated['normal_balance'], $isBank)) {
 164 |             return $this->failure($request, $bankError, 'is_bank_account');
 165 |         }
 166 | 
 167 |         $row = [
 168 |             'account_code' => $code,
 169 |             'account_name' => trim($validated['account_name']),
 170 |             'account_type' => $validated['account_type'],
 171 |             'normal_balance' => $validated['normal_balance'],
 172 |             'is_active' => $active,
 173 |             'created_at' => now(),
 174 |             'updated_at' => now(),
 175 |         ];
 176 |         if (Schema::hasColumn('chart_of_accounts', 'is_bank_account')) {
 177 |             $row['is_bank_account'] = $isBank;
 178 |         }
 179 |         $id = DB::table('chart_of_accounts')->insertGetId($row);
```

Aturan penanda Rekening Bank dan perlindungan akun inti — `app/Http/Controllers/ChartOfAccountController.php:262-277`

```text
 262 |     private function bankFlagError(string $code, string $type, string $normalBalance, bool $isBank): ?string
 263 |     {
 264 |         if ($code === BankAccounts::DEFAULT_BANK && ! $isBank) {
 265 |             return 'Akun 1102 (Bank utama) wajib tetap bertanda Rekening Bank.';
 266 |         }
 267 |         if ($isBank && $code !== BankAccounts::DEFAULT_BANK) {
 268 |             if ($type !== 'asset' || $normalBalance !== 'debit') {
 269 |                 return 'Rekening bank harus berupa akun Aset bersaldo normal Debet.';
 270 |             }
 271 |             if (in_array($code, self::SYSTEM_ACCOUNTS, true)) {
 272 |                 return 'Akun inti non-bank tidak boleh ditandai sebagai rekening bank.';
 273 |             }
 274 |         }
 275 | 
 276 |         return null;
 277 |     }
```

Resolusi terpusat rekening bank aktif — `app/Services/V2/BankAccounts.php:73-87`

```text
  73 |     public function resolve(?string $code, string $field = 'bank_account_code'): string
  74 |     {
  75 |         $trimmed = trim((string) ($code ?? ''));
  76 |         if ($trimmed === '') {
  77 |             return self::DEFAULT_BANK;
  78 |         }
  79 | 
  80 |         if (! $this->isBankAccount($trimmed, true)) {
  81 |             throw ValidationException::withMessages([
  82 |                 $field => 'Pilih rekening bank yang aktif dari Bagan Akun.',
  83 |             ]);
  84 |         }
  85 | 
  86 |         return $trimmed;
  87 |     }
```

## 02-penerimaan-pembayaran — Penerimaan supplier (termasuk ongkir) dan pembayaran invoice V2 — alur dari current working tree

### RP01 — Buka tagihan, penerimaan dan daftar pembayaran

Tagihan dipaginasi di server lintas tanggal; last_payment_id ialah pembayaran terakhir selain rejected, dilengkapi riwayat pembayaran beserta penanda bukti transfer dan rekening bank. Pembayaran 50 terakhir dibatasi requested_by untuk kasir. Admin membaca 50 penerimaan final dan 50 draf draft/cancelled; final memiliki audit konfirmasi melalui receipt_id. Tidak mengubah data transaksi.

Peran aktif yang benar-benar diotorisasi — `app/Services/V2/Operations.php:35-50`

```text
  35 |     public function role(int $actor): string
  36 |     {
  37 |         $role = DB::table('v2_user_roles')->join('users', 'users.id', '=', 'v2_user_roles.user_id')->where('v2_user_roles.user_id', $actor)->where('users.is_active', true)->value('v2_user_roles.role');
  38 |         if (! is_string($role) || ! in_array($role, ['admin', 'cashier'], true)) {
  39 |             abort(403);
  40 |         }
  41 | 
  42 |         return $role;
  43 |     }
  44 | 
  45 |     public function requireRole(int $actor, array $roles): void
  46 |     {
  47 |         if (! in_array($this->role($actor), $roles, true)) {
  48 |             abort(403);
  49 |         }
  50 |     }
```

Redirect, kas harian dan tagihan terpaginasi — `app/Http/Controllers/V2/WorkspaceController.php:39-55`

```text
  39 |     public function index(Request $request)
  40 |     {
  41 |         $role = $this->operations->role($request->user()->id);
  42 |         if (config('v2.enabled') && $request->routeIs('v2.index')) {
  43 |             return redirect()->route('pos.index', $request->query());
  44 |         }
  45 | 
  46 |         $shifts = DB::table('v2_shifts as s')->leftJoin('users as u', 'u.id', '=', 's.user_id')
  47 |             ->when($role !== 'admin', fn ($q) => $q->where(fn ($visible) => $visible->where('s.is_shared', true)->orWhere('s.user_id', $request->user()->id)))
  48 |             ->orderByDesc('s.id')->limit(30)->get(['s.*', 'u.name as user_name']);
  49 |         $reviewShifts = $role === 'admin'
  50 |             ? DB::table('v2_shifts')->where('status', 'review')->orderBy('closed_at')->orderBy('id')->get(['id', 'opened_at', 'counted_cash', 'difference'])
  51 |             : collect();
  52 |         $activeStoreShift = $this->operations->activeStoreShift();
  53 |         $todayCashDay = $activeStoreShift ? null : $this->todayCashDay();
  54 |         ['invoices' => $invoices, 'invoiceFilters' => $invoiceFilters] = $this->invoiceData($request);
  55 |         $payments = DB::table('v2_payments as pm')
```

Riwayat final dan draf dipisahkan — `app/Http/Controllers/V2/WorkspaceController.php:81-97`

```text
  81 |         $receiptHistory = $role === 'admin' ? DB::table('inventory_receipts')->leftJoin('users as void_user', 'void_user.id', '=', 'inventory_receipts.voided_by')
  82 |             ->orderByDesc('inventory_receipts.receipt_date')->orderByDesc('inventory_receipts.id')->limit(50)
  83 |             ->get(['inventory_receipts.*', 'void_user.name as voided_by_name']) : collect();
  84 |         // Dokumen yang sudah diposting selalu final. Draf disimpan terpisah dan belum masuk stok/utang.
  85 |         $receiptDrafts = $role === 'admin' && Schema::hasTable('v2_receipt_drafts')
  86 |             ? DB::table('v2_receipt_drafts as d')->join('v2_parties as p', 'p.id', '=', 'd.supplier_id')
  87 |                 ->leftJoin('users as u', 'u.id', '=', 'd.created_by')
  88 |                 ->whereIn('d.status', ['draft', 'cancelled'])->orderByDesc('d.id')->limit(50)
  89 |                 ->get(['d.*', 'p.name as supplier_name', 'u.name as author_name'])
  90 |             : collect();
  91 |         foreach ($receiptDrafts as $draft) {
  92 |             $draft->items = json_decode($draft->items, true, 512, JSON_THROW_ON_ERROR);
  93 |         }
  94 |         $draftProducts = $receiptDrafts->isEmpty() ? collect() : DB::table('products')
  95 |             ->whereIn('id', $receiptDrafts->flatMap(fn ($draft) => array_column($draft->items, 'product_id'))->unique())
  96 |             ->get(['id', 'name', 'sku', 'unit', 'cost_price', 'stock_quantity'])->keyBy('id');
  97 |         $receiptFinalAudit = $role === 'admin' && Schema::hasTable('v2_receipt_drafts')
```

### RP02 — Cari barang supplier atau daftar perlu restok

Pencarian supplier hanya mengembalikan relasi produk-supplier milik id tersebut, supplier aktif dan produk aktif (maksimal 50 hasil). Daftar restok menyaring produk aktif berstok habis/menipis dengan tautan supplier aktif. Tidak membuat penerimaan atau jurnal.

Rute pencarian produk supplier — `app/Http/Controllers/V2/WorkspaceController.php:668-673`

```text
 668 |     public function supplierProducts(Request $request, int $id): JsonResponse
 669 |     {
 670 |         $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
 671 | 
 672 |         return $this->run(fn () => app(SupplyTrips::class)->productsFor($request->user()->id, $id, (string) ($data['q'] ?? '')));
 673 |     }
```

Akses admin dan produk aktif tertaut — `app/Services/V2/SupplyTrips.php:448-463`

```text
 448 |     public function productsFor(int $actor, int $supplierId, string $term = '', int $limit = 30): array
 449 |     {
 450 |         $this->requireAdmin($actor);
 451 | 
 452 |         $query = DB::table('v2_product_suppliers')
 453 |             ->join('products', 'products.id', '=', 'v2_product_suppliers.product_id')
 454 |             ->join('v2_parties', 'v2_parties.id', '=', 'v2_product_suppliers.supplier_id')
 455 |             ->where('v2_product_suppliers.supplier_id', $supplierId)
 456 |             ->where('v2_parties.kind', 'supplier')
 457 |             ->where('v2_parties.is_active', true)
 458 |             ->where('products.is_active', true);
 459 | 
 460 |         $term = trim($term);
 461 |         if ($term !== '') {
 462 |             $query->where(fn ($match) => $match->where('products.name', 'like', '%'.$term.'%')->orWhere('products.sku', 'like', '%'.$term.'%'));
 463 |         }
```

Jumlah hasil dan payload pilihan — `app/Services/V2/SupplyTrips.php:465-482`

```text
 465 |         $total = (clone $query)->count();
 466 |         $rows = $query->orderBy('products.name')->limit(max(1, min($limit, 50)))
 467 |             ->get(['products.id', 'products.sku', 'products.name', 'products.unit', 'products.stock_quantity', 'products.min_stock', 'products.cost_price']);
 468 | 
 469 |         return [
 470 |             'total' => $total,
 471 |             'results' => $rows->map(fn ($row) => [
 472 |                 'id' => (int) $row->id,
 473 |                 'sku' => (string) $row->sku,
 474 |                 'name' => (string) $row->name,
 475 |                 'unit' => (string) $row->unit,
 476 |                 'stock' => (string) $row->stock_quantity,
 477 |                 'minimum' => (string) BigDecimal::of((string) $row->min_stock)->strippedOfTrailingZeros(),
 478 |                 'status' => BigDecimal::of((string) $row->stock_quantity)->isLessThanOrEqualTo(0) ? 'out'
 479 |                     : (BigDecimal::of((string) $row->stock_quantity)->isLessThanOrEqualTo((string) $row->min_stock) ? 'low' : 'available'),
 480 |                 'cost' => (string) $row->cost_price,
 481 |             ])->all(),
 482 |         ];
```

### RP03 — Simpan draf penerimaan baru (termasuk ongkir opsional)

Transaksi mengunci user, menyimpan snapshot (termasuk rincian ongkir) di v2_receipt_drafts status draft/version 1. Kunci per created_by dengan hash sama mengembalikan draf yang tersimpan; hash berbeda gagal validasi. Tidak membuat receipt final, utang, stok, HPP, kas/bank, atau jurnal.

Endpoint receipts menyimpan draf — `app/Http/Controllers/V2/WorkspaceController.php:634-639`

```text
 634 |     public function receive(Request $request): JsonResponse
 635 |     {
 636 |         $this->operations->requireRole($request->user()->id, ['admin']);
 637 | 
 638 |         return $this->run(fn () => app(ReceiptDrafts::class)->save($request->user()->id, $request->all()));
 639 |     }
```

Idempotensi dan insert draf — `app/Services/V2/ReceiptDrafts.php:23-42`

```text
  23 |             $snapshot = $this->snapshot($data);
  24 |             $hashSnapshot = $snapshot;
  25 |             if (($hashSnapshot['freight_amount'] ?? '0.00') === '0.00') {
  26 |                 unset($hashSnapshot['freight_amount'], $hashSnapshot['freight_method'], $hashSnapshot['freight_account'], $hashSnapshot['freight_bank_account_code']);
  27 |             }
  28 |             $hash = hash('sha256', json_encode($hashSnapshot, JSON_THROW_ON_ERROR));
  29 |             if ($id === null) {
  30 |                 $key = $data['idempotency_key'] ?? null;
  31 |                 if (! is_string($key) || preg_match('/^[\x20-\x7E]{1,100}$/', $key) !== 1) {
  32 |                     $this->invalid('Kunci idempotensi wajib 1..100 karakter ASCII.');
  33 |                 }
  34 |                 $row = DB::table('v2_receipt_drafts')->where('created_by', $actor)->where('idempotency_key', $key)->lockForUpdate()->first();
  35 |                 if ($row !== null) {
  36 |                     if (! hash_equals($row->request_hash, $hash)) {
  37 |                         $this->invalid('Kunci idempotensi sudah dipakai untuk muatan berbeda.');
  38 |                     }
  39 | 
  40 |                     return $this->result($row);
  41 |                 }
  42 |                 $id = DB::table('v2_receipt_drafts')->insertGetId($snapshot + ['created_by' => $actor, 'idempotency_key' => $key, 'request_hash' => $hash, 'status' => 'draft', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
```

Validasi ongkir pembelian dan rekening bank — `app/Services/V2/SupplyTrips.php:620-650`

```text
 620 |     public function freight(array $data): array
 621 |     {
 622 |         $amount = $this->amount($data['freight_amount'] ?? '0', 2, 'Ongkir');
 623 |         if ($amount->isNegative()) {
 624 |             $this->invalid('Ongkir tidak boleh negatif.');
 625 |         }
 626 |         if ($amount->isZero()) {
 627 |             return ['freight_amount' => '0.00', 'freight_method' => null, 'freight_account' => null, 'freight_bank_account_code' => null];
 628 |         }
 629 |         if (! Schema::hasColumn('v2_receipt_drafts', 'freight_amount') || ! Schema::hasColumn('inventory_receipts', 'freight_amount')) {
 630 |             $this->invalid('Fitur ongkir belum diaktifkan. Migrasi ongkir perlu dijalankan admin terlebih dahulu.');
 631 |         }
 632 |         $method = $data['freight_method'] ?? null;
 633 |         $account = $data['freight_account'] ?? null;
 634 |         if (! in_array($method, ['supplier', 'cash', 'bank'], true)) {
 635 |             $this->invalid('Pilih pembayaran ongkir: supplier, kas, atau bank.');
 636 |         }
 637 |         if (! is_string($account) || ! DB::table('chart_of_accounts')->where('account_code', $account)
 638 |             ->where('account_type', 'expense')->where('normal_balance', 'debit')->where('is_active', true)->exists()) {
 639 |             $this->invalid('Pilih akun beban ongkir yang aktif.');
 640 |         }
 641 |         $bankAccountCode = $method === 'bank'
 642 |             ? app(BankAccounts::class)->resolve(isset($data['freight_bank_account_code']) ? (string) $data['freight_bank_account_code'] : null, 'freight_bank_account_code')
 643 |             : null;
 644 | 
 645 |         return [
 646 |             'freight_amount' => $this->str($amount),
 647 |             'freight_method' => $method,
 648 |             'freight_account' => $account,
 649 |             'freight_bank_account_code' => $bankAccountCode,
 650 |         ];
```

### RP04 — Ubah draf penerimaan

Mengunci user serta baris draf; memperbarui snapshot dan menaikkan version. Tidak mengubah request_hash/idempotency_key awal. Status cancelled/confirmed dan versi usang ditolak. Tidak ada efek ke stok, HPP, utang atau jurnal.

Update snapshot dan version — `app/Services/V2/ReceiptDrafts.php:43-50`

```text
  43 |             } else {
  44 |                 $row = $this->locked($id);
  45 |                 $this->editable($row, $data);
  46 |                 DB::table('v2_receipt_drafts')->where('id', $id)->update($snapshot + ['version' => $row->version + 1, 'updated_at' => now()]);
  47 |             }
  48 | 
  49 |             return $this->result(DB::table('v2_receipt_drafts')->where('id', $id)->first());
  50 |         });
```

Kunci draf dan guard status/versi — `app/Services/V2/ReceiptDrafts.php:191-209`

```text
 191 |     private function locked(int $id): object
 192 |     {
 193 |         $row = DB::table('v2_receipt_drafts')->where('id', $id)->lockForUpdate()->first();
 194 |         if ($row === null) {
 195 |             $this->invalid('Draf penerimaan tidak ditemukan.');
 196 |         }
 197 | 
 198 |         return $row;
 199 |     }
 200 | 
 201 |     private function editable(object $row, array $input): void
 202 |     {
 203 |         if ($row->status !== 'draft') {
 204 |             $this->invalid('Hanya draf yang dapat diubah; penerimaan final terkunci.');
 205 |         }
 206 |         if (filter_var($input['version'] ?? null, FILTER_VALIDATE_INT) !== (int) $row->version) {
 207 |             $this->invalid('Versi draf telah berubah. Muat ulang sebelum melanjutkan.');
 208 |         }
 209 |     }
```

UI mengirim snapshot, ongkir dan version ke endpoint draf — `resources/views/v2/sprint2.blade.php:873-891`

```text
 873 |         const payload = {
 874 |             supplier_id: receipt.elements.supplier_id.value,
 875 |             receipt_date: receipt.elements.receipt_date.value,
 876 |             supplier_invoice_number: receipt.elements.supplier_invoice_number.value.trim() || null,
 877 |             confirm_duplicate: receipt.dataset.confirmDuplicate === '1',
 878 |             items: items,
 879 |             idempotency_key: receipt.dataset.key,
 880 |             version: receipt.dataset.draftVersion || null,
 881 |             notes: receipt.dataset.draftNotes || null
 882 |         };
 883 |         if (receipt.elements.freight_amount && !receipt.elements.freight_amount.closest('fieldset')?.disabled) {
 884 |             payload.freight_amount = receipt.elements.freight_amount.value || '0';
 885 |             payload.freight_method = receipt.elements.freight_method.value;
 886 |             payload.freight_account = receipt.elements.freight_account.value || null;
 887 |             if (receipt.elements.freight_bank_account_code && !receipt.elements.freight_bank_account_code.disabled) {
 888 |                 payload.freight_bank_account_code = receipt.elements.freight_bank_account_code.value || null;
 889 |             }
 890 |         }
 891 |         receipt._lastError = null;
```

### RP05 — Batalkan draf penerimaan

Status cancelled, version bertambah dan cancelled_by/cancelled_at/cancel_reason dicatat; draf tidak dihapus. Confirmed tidak dapat dibatalkan. Stok, HPP, receipt final, utang, kas/bank dan jurnal tidak berubah.

Idempoten cancelled dan audit pembatalan — `app/Services/V2/ReceiptDrafts.php:85-103`

```text
  85 |     public function cancel(int $actor, int $id, array $input): array
  86 |     {
  87 |         $this->requireAdmin($actor);
  88 | 
  89 |         return DB::transaction(function () use ($actor, $id, $input) {
  90 |             DB::table('users')->where('id', $actor)->lockForUpdate()->first();
  91 |             $row = $this->locked($id);
  92 |             if ($row->status === 'cancelled') {
  93 |                 return $this->result($row);
  94 |             }
  95 |             $this->editable($row, $input);
  96 |             $reason = $input['reason'] ?? null;
  97 |             if (! is_string($reason) || mb_strlen(trim($reason)) < 5 || mb_strlen(trim($reason)) > 255) {
  98 |                 $this->invalid('Alasan pembatalan wajib 5..255 karakter.');
  99 |             }
 100 |             DB::table('v2_receipt_drafts')->where('id', $id)->update(['status' => 'cancelled', 'version' => $row->version + 1, 'cancelled_by' => $actor, 'cancelled_at' => now(), 'cancel_reason' => trim($reason), 'updated_at' => now()]);
 101 | 
 102 |             return $this->result($this->locked($id));
 103 |         });
```

Hanya draft dengan versi cocok — `app/Services/V2/ReceiptDrafts.php:201-209`

```text
 201 |     private function editable(object $row, array $input): void
 202 |     {
 203 |         if ($row->status !== 'draft') {
 204 |             $this->invalid('Hanya draf yang dapat diubah; penerimaan final terkunci.');
 205 |         }
 206 |         if (filter_var($input['version'] ?? null, FILTER_VALIDATE_INT) !== (int) $row->version) {
 207 |             $this->invalid('Versi draf telah berubah. Muat ulang sebelum melanjutkan.');
 208 |         }
 209 |     }
```

### RP06 — Periksa konfirmasi final dan nota ganda

SupplyTrips::receive dipanggil dengan kunci draft-receipt-{id}. Nota opsional yang sama untuk supplier sama, lower/trim dan hanya receipt belum void, memicu duplicate_receipt bila confirm_duplicate tidak benar; transaksi gagal sehingga draf tetap draft. Replay v2_requests dengan hash sama mengembalikan hasil lama.

Persetujuan final wajib pada endpoint — `app/Http/Controllers/V2/WorkspaceController.php:646-652`

```text
 646 |     public function confirmReceiptDraft(Request $request, int $id): JsonResponse
 647 |     {
 648 |         $this->operations->requireRole($request->user()->id, ['admin']);
 649 |         $request->validate(['confirm_final' => ['required', 'accepted']]);
 650 | 
 651 |         return $this->run(fn () => app(ReceiptDrafts::class)->confirm($request->user()->id, $id, $request->all()));
 652 |     }
```

Replay confirmed dan snapshot tersimpan termasuk ongkir — `app/Services/V2/ReceiptDrafts.php:58-77`

```text
  58 |         return DB::transaction(function () use ($actor, $id, $input) {
  59 |             DB::table('users')->where('id', $actor)->lockForUpdate()->first();
  60 |             $row = $this->locked($id);
  61 |             if ($row->status === 'confirmed') {
  62 |                 return $this->result($row);
  63 |             }
  64 |             $this->editable($row, $input);
  65 |             $posted = $this->supplyTrips->receive($actor, [
  66 |                 'supplier_id' => (int) $row->supplier_id,
  67 |                 'receipt_date' => $row->receipt_date,
  68 |                 'notes' => $row->notes,
  69 |                 'supplier_invoice_number' => $row->supplier_invoice_number,
  70 |                 'items' => json_decode($row->items, true, 512, JSON_THROW_ON_ERROR),
  71 |                 'idempotency_key' => 'draft-receipt-'.$id,
  72 |                 'confirm_duplicate' => $input['confirm_duplicate'] ?? false,
  73 |                 'freight_amount' => (string) ($row->freight_amount ?? '0'),
  74 |                 'freight_method' => $row->freight_method ?? null,
  75 |                 'freight_account' => $row->freight_account ?? null,
  76 |                 'freight_bank_account_code' => $row->freight_bank_account_code ?? null,
  77 |             ]);
```

Guard nota supplier ganda pada konfirmasi — `app/Services/V2/SupplyTrips.php:109-118`

```text
 109 |             // Penjaga entri dobel: nota supplier yang sama sudah tercatat (penerimaan yang dibatalkan tidak dihitung).
 110 |             if ($nota !== null && ! $confirmed) {
 111 |                 $same = DB::table('inventory_receipts as r')->join('v2_invoices as i', 'i.receipt_id', '=', 'r.id')
 112 |                     ->where('i.party_id', $supplierId)->whereNull('r.voided_at')
 113 |                     ->whereRaw('lower(trim(r.supplier_invoice_number)) = ?', [mb_strtolower($nota)])
 114 |                     ->orderByDesc('r.id')->first(['r.receipt_number', 'r.receipt_date', 'r.total_amount']);
 115 |                 if ($same) {
 116 |                     throw ValidationException::withMessages(['duplicate_receipt' => 'Nota '.$nota.' dari '.$supplier->name.' sudah dicatat sebagai '.$same->receipt_number.' tanggal '.Carbon::parse($same->receipt_date)->locale('id')->translatedFormat('j M Y').' ('.Money::display($same->total_amount).'). Bila memang nota berbeda, tekan tombol Ya, tetap konfirmasi.']);
 117 |                 }
 118 |             }
```

### RP07 — Posting penerimaan final, HPP, utang, dan ongkir pembelian

Dalam satu transaksi: receipt RCV dan item dibuat; stok naik, HPP rata-rata tertimbang diperbarui (ongkir dicatat sebagai beban, TIDAK masuk HPP), mutasi v2_receipt masuk. Ledger debit Persediaan 1301 sebesar nilai barang, kredit Utang 2101 sebesar nilai barang (+ ongkir bila metode supplier). Bila ongkir > 0: tambah debit akun beban ongkir; bila metode cash/bank, tambah kredit 1101 atau rekening bank terpilih (dan mutasi out laci jika cash). Invoice AP payable dibuat sebesar payableTotal; draf menjadi confirmed. Kegagalan menggulung seluruh transaksi.

Stok dan HPP tertimbang serta awal mutasi — `app/Services/V2/SupplyTrips.php:218-235`

```text
 218 |                 $product = $products[$row['product_id']];
 219 |                 $oldStock = Money::value((string) $product->stock_quantity, 4);
 220 |                 $oldCost = Money::value((string) $product->cost_price, 4);
 221 |                 $newStock = $oldStock->plus($row['quantity']);
 222 |                 $value = $oldStock->multipliedBy($oldCost)
 223 |                     ->plus($row['quantity']->multipliedBy($row['unit_cost']));
 224 |                 $newCost = $value->dividedBy($newStock, 4, RoundingMode::HalfUp);
 225 | 
 226 |                 DB::table('products')->where('id', $row['product_id'])->update([
 227 |                     'stock_quantity' => (string) $newStock->toScale(4, RoundingMode::HalfUp),
 228 |                     'cost_price' => (string) $newCost,
 229 |                     'updated_at' => $now,
 230 |                 ]);
 231 | 
 232 |                 DB::table('stock_movements')->insert([
 233 |                     'product_id' => $row['product_id'],
 234 |                     'reference_type' => 'v2_receipt',
 235 |                     'reference_id' => $receiptId,
```

Jurnal penerimaan, beban ongkir, dan mutasi laci bila tunai — `app/Services/V2/SupplyTrips.php:244-262`

```text
 244 |             $lines = [
 245 |                 ['account_code' => self::INVENTORY, 'debit' => $this->str($total), 'credit' => '0.00'],
 246 |                 ['account_code' => self::PAYABLE, 'debit' => '0.00', 'credit' => $this->str($payableTotal)],
 247 |             ];
 248 |             if ($freightAmount->isPositive()) {
 249 |                 $lines[] = ['account_code' => $freight['freight_account'], 'debit' => $this->str($freightAmount), 'credit' => '0.00'];
 250 |                 if ($freight['freight_method'] !== 'supplier') {
 251 |                     $lines[] = ['account_code' => $freightCredit, 'debit' => '0.00', 'credit' => $this->str($freightAmount)];
 252 |                 }
 253 |             }
 254 |             $journalId = Ledger::post('v2_receipt', $receiptId, 'Penerimaan barang '.$date.' dari '.$supplier->name, $lines, $date);
 255 |             if ($shift !== null) {
 256 |                 DB::table('v2_shift_movements')->insert([
 257 |                     'shift_id' => $shift->id, 'direction' => 'out', 'kind' => 'adjustment',
 258 |                     'amount' => $this->str($freightAmount), 'reference_type' => 'v2_receipt_freight',
 259 |                     'reference_id' => $receiptId, 'note' => 'Ongkir penerimaan '.$receiptId,
 260 |                     'created_at' => $now, 'updated_at' => $now,
 261 |                 ]);
 262 |             }
```

Konfirmasi atomik dan audit draf — `app/Services/V2/ReceiptDrafts.php:65-81`

```text
  65 |             $posted = $this->supplyTrips->receive($actor, [
  66 |                 'supplier_id' => (int) $row->supplier_id,
  67 |                 'receipt_date' => $row->receipt_date,
  68 |                 'notes' => $row->notes,
  69 |                 'supplier_invoice_number' => $row->supplier_invoice_number,
  70 |                 'items' => json_decode($row->items, true, 512, JSON_THROW_ON_ERROR),
  71 |                 'idempotency_key' => 'draft-receipt-'.$id,
  72 |                 'confirm_duplicate' => $input['confirm_duplicate'] ?? false,
  73 |                 'freight_amount' => (string) ($row->freight_amount ?? '0'),
  74 |                 'freight_method' => $row->freight_method ?? null,
  75 |                 'freight_account' => $row->freight_account ?? null,
  76 |                 'freight_bank_account_code' => $row->freight_bank_account_code ?? null,
  77 |             ]);
  78 |             DB::table('v2_receipt_drafts')->where('id', $id)->update(['status' => 'confirmed', 'version' => $row->version + 1, 'receipt_id' => $posted['receipt_id'], 'confirmed_by' => $actor, 'confirmed_at' => now(), 'updated_at' => now()]);
  79 | 
  80 |             return $this->result($this->locked($id));
  81 |         });
```

### RP08 — Tolak void penerimaan final

Receipt yang ada selalu gagal validasi: penerimaan sudah dikonfirmasi dan terkunci, gunakan koreksi/retur. Tidak ada reversal jurnal, pemulihan HPP/stok, pembatalan invoice, update voided_at, atau transisi kembali ke draft.

Endpoint lama dipertahankan tetapi ditolak — `app/Http/Controllers/V2/WorkspaceController.php:660-665`

```text
 660 |     public function voidReceipt(Request $request, int $id): JsonResponse
 661 |     {
 662 |         $this->operations->requireRole($request->user()->id, ['admin']);
 663 | 
 664 |         return $this->run(fn () => app(SupplyTrips::class)->voidReceipt($request->user()->id, $id, $request->all()));
 665 |     }
```

Badan voidReceipt selalu menolak receipt yang ada — `app/Services/V2/SupplyTrips.php:610-617`

```text
 610 |     public function voidReceipt(int $actor, int $receiptId, array $input): array
 611 |     {
 612 |         $this->requireAdmin($actor);
 613 |         $receipt = DB::table('inventory_receipts')->where('id', $receiptId)->first();
 614 |         abort_unless($receipt, 404);
 615 | 
 616 |         $this->invalid('Penerimaan sudah dikonfirmasi dan terkunci. Pembatalan biasa tidak diizinkan; gunakan proses koreksi atau retur.');
 617 |     }
```

### RP09 — Validasi pembayaran, rekening bank, bukti transfer, dan entri ganda

Kunci pembayaran dicari global: pemohon harus actor sama (lainnya 403), hash sama mengembalikan payment lama; isi berbeda ditolak. Arah invoice harus receivable/payable; main untuk receivable ditolak. Jika seen_payment_id ada tanpa confirm_duplicate, pembayaran lebih baru selain rejected memicu duplicate_payment. Nilai baru harus <= sisa invoice. Bukti foto disimpan atomik via PaymentProofs::record.

Validasi pembayaran, bank_account_code dan foto bukti transfer — `app/Http/Controllers/V2/WorkspaceController.php:532-545`

```text
 532 |     public function collect(Request $request, int $id): JsonResponse
 533 |     {
 534 |         $this->operations->requireRole($request->user()->id, ['admin', 'cashier']);
 535 |         $data = $request->validate(['amount' => ['required', 'string', 'max:30'], 'method' => ['required', Rule::in(['cash', 'transfer', 'main'])], 'bank_account_code' => ['nullable', 'string', 'max:20'], 'idempotency_key' => ['required', 'string', 'min:8', 'max:100'], 'reference' => ['nullable', 'string', 'max:255'], 'seen_payment_id' => ['nullable', 'integer', 'min:0'], 'confirm_duplicate' => ['nullable', 'boolean']]);
 536 |         $needsProof = $data['method'] === 'transfer' && DB::table('v2_invoices')->where('id', $id)->where('direction', 'receivable')->exists();
 537 |         $photo = $this->validateTransferProof($request, $needsProof);
 538 | 
 539 |         return $this->run(fn () => app(PaymentProofs::class)->record($request->user()->id, $photo, function () use ($request, $id, $data): array {
 540 |             $seen = isset($data['seen_payment_id']) ? (int) $data['seen_payment_id'] : null;
 541 |             $paymentId = $this->operations->collect($request->user()->id, $id, $data['amount'], $data['method'], $data['idempotency_key'], $data['reference'] ?? null, $seen, (bool) ($data['confirm_duplicate'] ?? false), $data['bank_account_code'] ?? null);
 542 | 
 543 |             return ['payment_id' => $paymentId, 'data' => ['id' => $paymentId, 'status' => (string) DB::table('v2_payments')->where('id', $paymentId)->value('status')]];
 544 |         }));
 545 |     }
```

Kunci invoice dan replay pembayaran — `app/Services/V2/Operations.php:681-699`

```text
 681 |         return DB::transaction(function () use ($actor, $invoiceId, $value, $method, $key, $reference, $seenPaymentId, $confirmed, $bankAccountCode, $resolvedBank) {
 682 |             $invoice = DB::table('v2_invoices')->where('id', $invoiceId)->lockForUpdate()->first();
 683 |             if (! $invoice) {
 684 |                 $this->invalid('Tagihan tidak ditemukan.');
 685 |             }
 686 |             $hashParts = [$invoiceId, $this->str($value), $method, $reference];
 687 |             if ($resolvedBank !== null && trim((string) ($bankAccountCode ?? '')) !== '') {
 688 |                 $hashParts[] = $resolvedBank;
 689 |             }
 690 |             $requestHash = hash('sha256', json_encode($hashParts, JSON_THROW_ON_ERROR));
 691 |             $existing = DB::table('v2_payments')->where('idempotency_key', $key)->first();
 692 |             if ($existing) {
 693 |                 abort_unless((int) $existing->requested_by === $actor, 403);
 694 |                 if ($existing->request_hash !== $requestHash) {
 695 |                     $this->invalid('Kunci pembayaran sudah dipakai untuk isi berbeda.');
 696 |                 }
 697 | 
 698 |                 return (int) $existing->id;
 699 |             }
```

Guard perubahan pembayaran sejak form dibuka — `app/Services/V2/Operations.php:1131-1143`

```text
1131 |     private function guardNewerPayments(int $invoiceId, int $seenPaymentId): void
1132 |     {
1133 |         $newer = DB::table('v2_payments as pm')->leftJoin('users as u', 'u.id', '=', 'pm.requested_by')
1134 |             ->where('pm.invoice_id', $invoiceId)->where('pm.id', '>', $seenPaymentId)->where('pm.status', '<>', 'rejected')
1135 |             ->orderByDesc('pm.id')->get(['pm.amount', 'pm.method', 'pm.status', 'pm.created_at', 'u.name']);
1136 |         if ($newer->isEmpty()) {
1137 |             return;
1138 |         }
1139 |         $last = $newer->first();
1140 |         $what = Money::display($last->amount).' ('.$this->methodLabel((string) $last->method).($last->status === 'posted' ? '' : ', menunggu').')';
1141 |         $more = $newer->count() > 1 ? ' dan '.($newer->count() - 1).' pembayaran lain' : '';
1142 |         throw ValidationException::withMessages(['duplicate_payment' => 'Sejak form ini dibuka, '.($last->name ?? 'pengguna lain').' sudah mencatat pembayaran '.$what.' pukul '.Carbon::parse($last->created_at)->format('H:i').$more.'. Pastikan ini bukan uang yang sama. Bila memang pembayaran lain, tekan tombol Ya, tetap catat.']);
1143 |     }
```

### RP10 — Terima piutang tunai

Payment posted dengan shift_id kas harian bersama dan approved_by actor dibuat; jurnal debit Kas 1101/kredit Piutang 1103, gerakan laci direction=in/kind=collection. paid invoice bertambah, status paid bila lunas atau open bila sebagian; bila sale_id ada, status penjualan dihitung paid/partial/unpaid.

Kas harian bersama dan payment posted tunai — `app/Services/V2/Operations.php:776-792`

```text
 776 |             $shift = $this->openShiftFor($actor, true);
 777 |             $paymentId = DB::table('v2_payments')->insertGetId([
 778 |                 'invoice_id' => $invoiceId,
 779 |                 'shift_id' => (int) $shift->id,
 780 |                 'amount' => $this->str($value),
 781 |                 'method' => 'cash',
 782 |                 'status' => 'posted',
 783 |                 'requested_by' => $actor,
 784 |                 'approved_by' => $actor,
 785 |                 'reference' => $reference,
 786 |                 'idempotency_key' => $key,
 787 |                 'request_hash' => $requestHash,
 788 |                 'journal_entry_id' => null,
 789 |                 'created_at' => now(),
 790 |                 'updated_at' => now(),
 791 |             ]);
 792 | 
```

Jurnal kas dan uang laci masuk — `app/Services/V2/Operations.php:793-809`

```text
 793 |             $entryId = Ledger::post('v2_payment', $paymentId, 'Penerimaan '.$invoice->number, [
 794 |                 ['account_code' => self::CASH, 'debit' => $this->str($value), 'credit' => '0.00'],
 795 |                 ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($value)],
 796 |             ]);
 797 |             DB::table('v2_payments')->where('id', $paymentId)->update(['journal_entry_id' => $entryId, 'updated_at' => now()]);
 798 | 
 799 |             DB::table('v2_shift_movements')->insert([
 800 |                 'shift_id' => (int) $shift->id,
 801 |                 'direction' => 'in',
 802 |                 'kind' => 'collection',
 803 |                 'amount' => $this->str($value),
 804 |                 'reference_type' => 'v2_payment',
 805 |                 'reference_id' => $paymentId,
 806 |                 'note' => 'Penerimaan '.$invoice->number,
 807 |                 'created_at' => now(),
 808 |                 'updated_at' => now(),
 809 |             ]);
```

Saldo invoice dan status sale diperbarui — `app/Services/V2/Operations.php:811-821`

```text
 811 |             DB::table('v2_invoices')->where('id', $invoiceId)->update([
 812 |                 'paid' => $this->str(Money::value((string) $invoice->paid, 2)->plus($value)),
 813 |                 'status' => $value->isEqualTo($remaining) ? 'paid' : 'open',
 814 |                 'updated_at' => now(),
 815 |             ]);
 816 | 
 817 |             if ($invoice->sale_id !== null) {
 818 |                 DB::table('v2_sales')->where('id', $invoice->sale_id)->update(['status' => $this->saleStatus(Money::value((string) $invoice->paid)->plus($value), Money::value((string) $invoice->amount)), 'updated_at' => now()]);
 819 |             }
 820 | 
 821 |             return $paymentId;
```

### RP11 — Catat transfer pelanggan menunggu (dengan rekening bank & foto bukti)

Payment pending dengan bank_account_code dan bukti foto di v2_payment_proofs, shift_id/approved_by/journal_entry_id null. Belum menambah paid invoice, belum mengubah sale atau laci/bank/jurnal. Menunggu approve atau reject admin atas payment_id yang sama.

Guard sumber dan sisa tagihan — `app/Services/V2/Operations.php:700-716`

```text
 700 |             if (! in_array($invoice->direction, ['receivable', 'payable'], true)) {
 701 |                 $this->invalid('Arah tagihan tidak valid.');
 702 |             }
 703 |             if ($invoice->direction === 'receivable' && $method === 'main') {
 704 |                 $this->invalid('Kas Utama hanya untuk pembayaran ke supplier.');
 705 |             }
 706 |             if ($seenPaymentId !== null && ! $confirmed) {
 707 |                 $this->guardNewerPayments($invoiceId, $seenPaymentId);
 708 |             }
 709 | 
 710 |             $remaining = Money::value((string) $invoice->amount, 2)->minus(Money::value((string) $invoice->paid, 2));
 711 |             if (! $remaining->isPositive()) {
 712 |                 $this->invalid('Tagihan sudah lunas.');
 713 |             }
 714 |             if ($value->isGreaterThan($remaining)) {
 715 |                 $this->invalid('Nominal melebihi sisa tagihan.');
 716 |             }
```

Transfer receivable pending dengan bank_account_code — `app/Services/V2/Operations.php:753-774`

```text
 753 |             if ($method === 'transfer') {
 754 |                 $paymentRow = [
 755 |                     'invoice_id' => $invoiceId,
 756 |                     'shift_id' => null,
 757 |                     'amount' => $this->str($value),
 758 |                     'method' => 'transfer',
 759 |                     'status' => 'pending',
 760 |                     'requested_by' => $actor,
 761 |                     'approved_by' => null,
 762 |                     'reference' => $reference,
 763 |                     'idempotency_key' => $key,
 764 |                     'request_hash' => $requestHash,
 765 |                     'journal_entry_id' => null,
 766 |                     'created_at' => now(),
 767 |                     'updated_at' => now(),
 768 |                 ];
 769 |                 if ($hasBankCol) {
 770 |                     $paymentRow['bank_account_code'] = $resolvedBank;
 771 |                 }
 772 | 
 773 |                 return DB::table('v2_payments')->insertGetId($paymentRow);
 774 |             }
```

### RP12 — Ajukan pembayaran supplier

Payment proposed disimpan dengan requested_by actor, bank_account_code (bila transfer), approved_by/journal null. Kasir tidak memanggil postPayable; paid invoice, kas/bank, jurnal dan gerakan laci belum berubah. Saldo buku besar/laci baru diperiksa saat admin menyetujui; pengajuan tidak mereservasi sisa utang.

Sumber dana payable dan status proposed — `app/Services/V2/Operations.php:719-736`

```text
 719 |             if ($invoice->direction === 'payable') {
 720 |                 // Hanya pembayaran laci yang terikat pada shift toko saat pengajuan.
 721 |                 $shiftId = $method === 'cash' ? $this->currentShiftId($actor) : null;
 722 |                 if ($method === 'cash' && $shiftId === null) {
 723 |                     $this->invalid('Shift toko belum dibuka admin.');
 724 |                 }
 725 | 
 726 |                 // Kasir mengajukan; admin membayar langsung tercatat (tunai dari laci shift-nya, transfer dari bank).
 727 |                 $paymentRow = [
 728 |                     'invoice_id' => $invoiceId,
 729 |                     'shift_id' => $shiftId,
 730 |                     'amount' => $this->str($value),
 731 |                     'method' => $method,
 732 |                     'status' => 'proposed',
 733 |                     'requested_by' => $actor,
 734 |                     'approved_by' => null,
 735 |                     'reference' => $reference,
 736 |                     'idempotency_key' => $key,
```

Admin saja langsung posting setelah insert — `app/Services/V2/Operations.php:742-751`

```text
 742 |                 if ($hasBankCol) {
 743 |                     $paymentRow['bank_account_code'] = $resolvedBank;
 744 |                 }
 745 |                 $paymentId = DB::table('v2_payments')->insertGetId($paymentRow);
 746 |                 if ($this->role($actor) === 'admin') {
 747 |                     $this->postPayable($actor, $invoice, DB::table('v2_payments')->where('id', $paymentId)->first());
 748 |                 }
 749 | 
 750 |                 return $paymentId;
 751 |             }
```

### RP13 — Bayar supplier dari Kas Utama

Payment menjadi posted dengan approved_by admin/journal_id. Jurnal debit Utang supplier 2101/kredit Kas 1101; invoice paid bertambah, status paid bila lunas atau open bila sebagian. Tidak mencatat mutasi laci. Kegagalan saldo/akun membatalkan insert dan posting.

Admin collect langsung postPayable — `app/Services/V2/Operations.php:745-751`

```text
 745 |                 $paymentId = DB::table('v2_payments')->insertGetId($paymentRow);
 746 |                 if ($this->role($actor) === 'admin') {
 747 |                     $this->postPayable($actor, $invoice, DB::table('v2_payments')->where('id', $paymentId)->first());
 748 |                 }
 749 | 
 750 |                 return $paymentId;
 751 |             }
```

main memakai akun kas 1101 tanpa laci — `app/Services/V2/Operations.php:927-939`

```text
 927 |         // Laci shift memakai uang laci kasir; Kas Utama dan Bank Utama memakai saldo toko dan tidak mengubah laci.
 928 |         $drawer = $source === 'cash';
 929 |         $bankService = app(BankAccounts::class);
 930 |         if ($source === 'transfer') {
 931 |             $account = trim((string) ($payment->bank_account_code ?? '')) ?: self::BANK;
 932 |             if (! $bankService->isBankAccount($account, true)) {
 933 |                 $this->invalid('Rekening bank sumber dana sudah tidak aktif di Bagan Akun.');
 934 |             }
 935 |             $label = $bankService->label($account);
 936 |         } else {
 937 |             $account = self::CASH;
 938 |             $label = 'Kas Utama (Tunai)';
 939 |         }
```

Saldo buku besar tidak boleh negatif — `app/Services/V2/Operations.php:996-1008`

```text
 996 |     private function guardAccount(string $code, BigDecimal $delta, ?string $label = null): void
 997 |     {
 998 |         if (! DB::table('chart_of_accounts')->where('account_code', $code)->lockForUpdate()->exists()) {
 999 |             $this->invalid('Akun '.$code.' belum tersedia di bagan akun.');
1000 |         }
1001 |         $balance = Money::value('0');
1002 |         foreach (DB::table('journal_entry_lines')->where('account_code', $code)->get(['debit', 'credit']) as $line) {
1003 |             $balance = $balance->plus((string) $line->debit)->minus((string) $line->credit);
1004 |         }
1005 |         if ($balance->plus($delta)->isNegative()) {
1006 |             $this->invalid('Saldo '.($label ?? 'akun '.$code).' tidak cukup untuk pembayaran keluar.');
1007 |         }
1008 |     }
```

### RP14 — Bayar supplier dari laci kas harian

Admin langsung memposting: debit Utang supplier 2101/kredit Kas 1101, payment posted, invoice paid bertambah/status open atau paid. Gerakan out/kind=supplier dibuat pada kas harian yang dipakai saat pengajuan. Kegagalan satu guard menggulung transaksi.

Guard kas harian/saldo laci dan jurnal — `app/Services/V2/Operations.php:940-957`

```text
 940 |         if ($drawer) {
 941 |             $drawer = DB::table('v2_shifts')->where('id', $payment->shift_id)->lockForUpdate()->first();
 942 |             if (! $drawer || $drawer->status !== 'open' || ($drawer->is_shared ? (int) $drawer->store_open_key !== 1 : (int) $drawer->user_id !== (int) $payment->requested_by)) {
 943 |                 $this->invalid('Shift pengajuan tidak terbuka; ajukan ulang.');
 944 |             }
 945 |             $drawerBalance = Money::value((string) $drawer->opening_cash);
 946 |             foreach (DB::table('v2_shift_movements')->where('shift_id', $drawer->id)->get(['direction', 'amount']) as $movement) {
 947 |                 $drawerBalance = $movement->direction === 'in' ? $drawerBalance->plus((string) $movement->amount) : $drawerBalance->minus((string) $movement->amount);
 948 |             }
 949 |             if ($drawerBalance->isLessThan($amount)) {
 950 |                 $this->invalid('Uang tunai laci tidak cukup untuk pembayaran.');
 951 |             }
 952 |         }
 953 |         $this->guardAccount($account, $amount->negated(), $label);
 954 |         $entryId = Ledger::post('v2_payment', $paymentId, 'Pembayaran utang '.$invoice->number, [
 955 |             ['account_code' => self::AP, 'debit' => $this->str($amount), 'credit' => '0.00'],
 956 |             ['account_code' => $account, 'debit' => '0.00', 'credit' => $this->str($amount)],
 957 |         ]);
```

Gerakan supplier keluar pada kas harian — `app/Services/V2/Operations.php:959-981`

```text
 959 |         if ($drawer) {
 960 |             if ($payment->shift_id === null) {
 961 |                 $this->invalid('Pengajuan tunai tanpa shift; ajukan ulang.');
 962 |             }
 963 |             $shift = DB::table('v2_shifts')->where('id', $payment->shift_id)->lockForUpdate()->first();
 964 |             if (! $shift || $shift->status !== 'open') {
 965 |                 $this->invalid('Shift pengajuan sudah ditutup; ajukan ulang.');
 966 |             }
 967 |             if (! $shift->is_shared && (int) $shift->user_id !== (int) $payment->requested_by) {
 968 |                 $this->invalid('Shift pengajuan bukan milik pemohon.');
 969 |             }
 970 |             DB::table('v2_shift_movements')->insert([
 971 |                 'shift_id' => (int) $shift->id,
 972 |                 'direction' => 'out',
 973 |                 'kind' => 'supplier',
 974 |                 'amount' => $this->str($amount),
 975 |                 'reference_type' => 'v2_payment',
 976 |                 'reference_id' => $paymentId,
 977 |                 'note' => 'Pembayaran utang '.$invoice->number,
 978 |                 'created_at' => now(),
 979 |                 'updated_at' => now(),
 980 |             ]);
 981 |         }
```

Payment posted dan invoice diperbarui — `app/Services/V2/Operations.php:983-994`

```text
 983 |         DB::table('v2_payments')->where('id', $paymentId)->update([
 984 |             'status' => 'posted',
 985 |             'approved_by' => $actor,
 986 |             'journal_entry_id' => $entryId,
 987 |             'updated_at' => now(),
 988 |         ]);
 989 |         DB::table('v2_invoices')->where('id', $invoice->id)->update([
 990 |             'paid' => $this->str(Money::value((string) $invoice->paid, 2)->plus($amount)),
 991 |             'status' => $amount->isEqualTo($remaining) ? 'paid' : 'open',
 992 |             'updated_at' => now(),
 993 |         ]);
 994 |     }
```

### RP15 — Bayar supplier dari rekening bank aktif

Jurnal debit Utang supplier 2101/kredit rekening bank terpilih, payment posted dengan approved_by admin/journal_id, paid invoice naik dan status open/paid. Tidak ada pending verifikasi kedua atau gerakan laci.

Transfer memilih rekening bank aktif terpilih — `app/Services/V2/Operations.php:929-939`

```text
 929 |         $bankService = app(BankAccounts::class);
 930 |         if ($source === 'transfer') {
 931 |             $account = trim((string) ($payment->bank_account_code ?? '')) ?: self::BANK;
 932 |             if (! $bankService->isBankAccount($account, true)) {
 933 |                 $this->invalid('Rekening bank sumber dana sudah tidak aktif di Bagan Akun.');
 934 |             }
 935 |             $label = $bankService->label($account);
 936 |         } else {
 937 |             $account = self::CASH;
 938 |             $label = 'Kas Utama (Tunai)';
 939 |         }
```

Jurnal bayar supplier — `app/Services/V2/Operations.php:953-957`

```text
 953 |         $this->guardAccount($account, $amount->negated(), $label);
 954 |         $entryId = Ledger::post('v2_payment', $paymentId, 'Pembayaran utang '.$invoice->number, [
 955 |             ['account_code' => self::AP, 'debit' => $this->str($amount), 'credit' => '0.00'],
 956 |             ['account_code' => $account, 'debit' => '0.00', 'credit' => $this->str($amount)],
 957 |         ]);
```

Finalisasi payment dan invoice — `app/Services/V2/Operations.php:983-994`

```text
 983 |         DB::table('v2_payments')->where('id', $paymentId)->update([
 984 |             'status' => 'posted',
 985 |             'approved_by' => $actor,
 986 |             'journal_entry_id' => $entryId,
 987 |             'updated_at' => now(),
 988 |         ]);
 989 |         DB::table('v2_invoices')->where('id', $invoice->id)->update([
 990 |             'paid' => $this->str(Money::value((string) $invoice->paid, 2)->plus($amount)),
 991 |             'status' => $amount->isEqualTo($remaining) ? 'paid' : 'open',
 992 |             'updated_at' => now(),
 993 |         ]);
 994 |     }
```

### RP16 — Setujui transfer piutang (dengan opsi koreksi rekening bank)

Jika admin mengirim bank_account_code, kode rekening divalidasi aktif dan diperbarui pada payment sebelum posting. Jurnal debit rekening bank terpilih/kredit Piutang 1103 dibuat, payment menjadi posted, approved_by dan journal_id diisi. paid invoice dan status sale terkait diperbarui. Tidak memerlukan kas harian atau gerakan laci.

Status dan sisa diperiksa ulang — `app/Services/V2/Operations.php:839-855`

```text
 839 |             $payment = DB::table('v2_payments')->where('id', $paymentId)->lockForUpdate()->first();
 840 | 
 841 |             if ($payment->status === 'posted') {
 842 |                 return;
 843 |             }
 844 |             if (! in_array($payment->status, ['pending', 'proposed'], true)) {
 845 |                 $this->invalid('Status pembayaran tidak dapat disetujui.');
 846 |             }
 847 |             $amount = Money::value((string) $payment->amount, 2);
 848 |             if (! $amount->isPositive()) {
 849 |                 $this->invalid('Nominal pembayaran tidak valid.');
 850 |             }
 851 |             $remaining = Money::value((string) $invoice->amount, 2)->minus(Money::value((string) $invoice->paid, 2));
 852 |             if ($amount->isGreaterThan($remaining)) {
 853 |                 $this->invalid('Nominal melebihi sisa tagihan.');
 854 |             }
 855 | 
```

Koreksi rekening bank opsional dan verifikasi transfer AR — `app/Services/V2/Operations.php:856-880`

```text
 856 |             $overrideBank = null;
 857 |             if ($payment->method === 'transfer' && $bankAccountCode !== null && trim($bankAccountCode) !== '') {
 858 |                 $overrideBank = app(BankAccounts::class)->resolve($bankAccountCode);
 859 |                 $payment->bank_account_code = $overrideBank;
 860 |                 if (Schema::hasColumn('v2_payments', 'bank_account_code')) {
 861 |                     DB::table('v2_payments')->where('id', $paymentId)->update([
 862 |                         'bank_account_code' => $overrideBank,
 863 |                         'updated_at' => now(),
 864 |                     ]);
 865 |                 }
 866 |             }
 867 | 
 868 |             $entryId = null;
 869 |             if ($invoice->direction === 'receivable') {
 870 |                 if ($payment->method !== 'transfer') {
 871 |                     $this->invalid('Hanya transfer yang menunggu verifikasi bank.');
 872 |                 }
 873 |                 $bankCode = trim((string) ($payment->bank_account_code ?? '')) ?: self::BANK;
 874 |                 if (! app(BankAccounts::class)->isBankAccount($bankCode, true)) {
 875 |                     $this->invalid('Rekening bank tujuan sudah tidak aktif di Bagan Akun.');
 876 |                 }
 877 |                 $entryId = Ledger::post('v2_payment', $paymentId, 'Verifikasi transfer '.$invoice->number, [
 878 |                     ['account_code' => $bankCode, 'debit' => $this->str($amount), 'credit' => '0.00'],
 879 |                     ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($amount)],
 880 |                 ]);
```

Payment invoice dan sale diperbarui — `app/Services/V2/Operations.php:889-903`

```text
 889 |             DB::table('v2_payments')->where('id', $paymentId)->update([
 890 |                 'status' => 'posted',
 891 |                 'approved_by' => $actor,
 892 |                 'journal_entry_id' => $entryId,
 893 |                 'updated_at' => now(),
 894 |             ]);
 895 |             DB::table('v2_invoices')->where('id', $invoice->id)->update([
 896 |                 'paid' => $this->str(Money::value((string) $invoice->paid, 2)->plus($amount)),
 897 |                 'status' => $amount->isEqualTo($remaining) ? 'paid' : 'open',
 898 |                 'updated_at' => now(),
 899 |             ]);
 900 | 
 901 |             if ($invoice->sale_id !== null) {
 902 |                 DB::table('v2_sales')->where('id', $invoice->sale_id)->update(['status' => $this->saleStatus(Money::value((string) $invoice->paid)->plus($amount), Money::value((string) $invoice->amount)), 'updated_at' => now()]);
 903 |             }
```

### RP17 — Setujui pengajuan supplier (dengan opsi koreksi rekening bank)

Jika method=transfer dan admin mengirim bank_account_code, rekening sumber diperbarui sebelum postPayable. Memanggil postPayable: main memakai Kas 1101, transfer memakai rekening bank aktif terpilih tanpa laci; cash memerlukan kas harian pengajuan masih open dan uang laci cukup. Semua sumber memerlukan saldo akun cukup. Payment posted, approved_by/journal_id terisi dan paid invoice diperbarui.

Approve hanya status layak dan sisa cukup — `app/Services/V2/Operations.php:839-855`

```text
 839 |             $payment = DB::table('v2_payments')->where('id', $paymentId)->lockForUpdate()->first();
 840 | 
 841 |             if ($payment->status === 'posted') {
 842 |                 return;
 843 |             }
 844 |             if (! in_array($payment->status, ['pending', 'proposed'], true)) {
 845 |                 $this->invalid('Status pembayaran tidak dapat disetujui.');
 846 |             }
 847 |             $amount = Money::value((string) $payment->amount, 2);
 848 |             if (! $amount->isPositive()) {
 849 |                 $this->invalid('Nominal pembayaran tidak valid.');
 850 |             }
 851 |             $remaining = Money::value((string) $invoice->amount, 2)->minus(Money::value((string) $invoice->paid, 2));
 852 |             if ($amount->isGreaterThan($remaining)) {
 853 |                 $this->invalid('Nominal melebihi sisa tagihan.');
 854 |             }
 855 | 
```

Koreksi rekening bank opsional lalu postPayable — `app/Services/V2/Operations.php:856-885`

```text
 856 |             $overrideBank = null;
 857 |             if ($payment->method === 'transfer' && $bankAccountCode !== null && trim($bankAccountCode) !== '') {
 858 |                 $overrideBank = app(BankAccounts::class)->resolve($bankAccountCode);
 859 |                 $payment->bank_account_code = $overrideBank;
 860 |                 if (Schema::hasColumn('v2_payments', 'bank_account_code')) {
 861 |                     DB::table('v2_payments')->where('id', $paymentId)->update([
 862 |                         'bank_account_code' => $overrideBank,
 863 |                         'updated_at' => now(),
 864 |                     ]);
 865 |                 }
 866 |             }
 867 | 
 868 |             $entryId = null;
 869 |             if ($invoice->direction === 'receivable') {
 870 |                 if ($payment->method !== 'transfer') {
 871 |                     $this->invalid('Hanya transfer yang menunggu verifikasi bank.');
 872 |                 }
 873 |                 $bankCode = trim((string) ($payment->bank_account_code ?? '')) ?: self::BANK;
 874 |                 if (! app(BankAccounts::class)->isBankAccount($bankCode, true)) {
 875 |                     $this->invalid('Rekening bank tujuan sudah tidak aktif di Bagan Akun.');
 876 |                 }
 877 |                 $entryId = Ledger::post('v2_payment', $paymentId, 'Verifikasi transfer '.$invoice->number, [
 878 |                     ['account_code' => $bankCode, 'debit' => $this->str($amount), 'credit' => '0.00'],
 879 |                     ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($amount)],
 880 |                 ]);
 881 |             } elseif ($invoice->direction === 'payable') {
 882 |                 $this->postPayable($actor, $invoice, $payment);
 883 | 
 884 |                 return;
 885 |             } else {
```

Kas harian, saldo akun dan jurnal saat approve — `app/Services/V2/Operations.php:940-957`

```text
 940 |         if ($drawer) {
 941 |             $drawer = DB::table('v2_shifts')->where('id', $payment->shift_id)->lockForUpdate()->first();
 942 |             if (! $drawer || $drawer->status !== 'open' || ($drawer->is_shared ? (int) $drawer->store_open_key !== 1 : (int) $drawer->user_id !== (int) $payment->requested_by)) {
 943 |                 $this->invalid('Shift pengajuan tidak terbuka; ajukan ulang.');
 944 |             }
 945 |             $drawerBalance = Money::value((string) $drawer->opening_cash);
 946 |             foreach (DB::table('v2_shift_movements')->where('shift_id', $drawer->id)->get(['direction', 'amount']) as $movement) {
 947 |                 $drawerBalance = $movement->direction === 'in' ? $drawerBalance->plus((string) $movement->amount) : $drawerBalance->minus((string) $movement->amount);
 948 |             }
 949 |             if ($drawerBalance->isLessThan($amount)) {
 950 |                 $this->invalid('Uang tunai laci tidak cukup untuk pembayaran.');
 951 |             }
 952 |         }
 953 |         $this->guardAccount($account, $amount->negated(), $label);
 954 |         $entryId = Ledger::post('v2_payment', $paymentId, 'Pembayaran utang '.$invoice->number, [
 955 |             ['account_code' => self::AP, 'debit' => $this->str($amount), 'credit' => '0.00'],
 956 |             ['account_code' => $account, 'debit' => '0.00', 'credit' => $this->str($amount)],
 957 |         ]);
```

### RP18 — Tolak pembayaran menunggu

Payment menjadi rejected dengan resolution_note/resolved_by/resolved_at. Tidak mengubah paid invoice, sale, stok, laci, saldo kas/bank atau jurnal karena pembayaran belum diposting. Reject ulang dengan actor/catatan berbeda gagal.

Input alasan penolakan — `app/Http/Controllers/V2/WorkspaceController.php:603-612`

```text
 603 |     public function reject(Request $request, int $id): JsonResponse
 604 |     {
 605 |         $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
 606 | 
 607 |         return $this->run(function () use ($request, $id, $data): array {
 608 |             $this->operations->rejectPayment($request->user()->id, $id, $data['reason']);
 609 | 
 610 |             return ['status' => 'rejected'];
 611 |         });
 612 |     }
```

Akses dan replay rejected — `app/Services/V2/Operations.php:255-274`

```text
 255 |     public function rejectPayment(int $actor, int $paymentId, string $reason): void
 256 |     {
 257 |         $this->requireRole($actor, ['admin']);
 258 |         $note = trim($reason);
 259 |         $length = mb_strlen($note);
 260 |         if ($length < 5 || $length > 1000) {
 261 |             $this->invalid('Alasan penolakan 5 sampai 1000 karakter.');
 262 |         }
 263 | 
 264 |         DB::transaction(function () use ($actor, $paymentId, $note) {
 265 |             $payment = DB::table('v2_payments')->where('id', $paymentId)->lockForUpdate()->first();
 266 |             if (! $payment) {
 267 |                 $this->invalid('Pembayaran tidak ditemukan.');
 268 |             }
 269 |             if ($payment->status === 'rejected') {
 270 |                 if ((int) $payment->resolved_by === $actor && $payment->resolution_note === $note) {
 271 |                     return;
 272 |                 }
 273 |                 $this->invalid('Pembayaran sudah ditolak dengan catatan berbeda.');
 274 |             }
```

Guard status dan audit penolakan — `app/Services/V2/Operations.php:275-286`

```text
 275 |             if (! in_array($payment->status, ['pending', 'proposed'], true)) {
 276 |                 $this->invalid('Hanya pembayaran pending atau proposed yang dapat ditolak.');
 277 |             }
 278 | 
 279 |             DB::table('v2_payments')->where('id', $paymentId)->update([
 280 |                 'status' => 'rejected',
 281 |                 'resolution_note' => $note,
 282 |                 'resolved_by' => $actor,
 283 |                 'resolved_at' => now(),
 284 |                 'updated_at' => now(),
 285 |             ]);
 286 |         });
```

## 01-kasir-shift — Kasir V2 — Penjualan dan lifecycle kas harian bersama sampai rekap penutupan (berbasis kode)

### KS01 — Masuk Kasir V2 dan pembatasan peran

Operations::role mensyaratkan users.is_active dan v2_user_roles admin/cashier; selain itu 403. Workspace menampilkan kas harian bersama (serta penjualan kasir sendiri bagi kasir, atau lintas kasir bagi admin). Cutover membuat rute legacy selain root hanya arsip GET admin; bukan jalur checkout V2.

Cutover halaman POS — `app/Http/Controllers/PosController.php:15-19`

```text
  15 |     public function index(Request $request)
  16 |     {
  17 |         if (config('v2.enabled')) {
  18 |             return app(WorkspaceController::class)->index($request);
  19 |         }
```

Peran harus aktif dan berwenang — `app/Services/V2/Operations.php:35-50`

```text
  35 |     public function role(int $actor): string
  36 |     {
  37 |         $role = DB::table('v2_user_roles')->join('users', 'users.id', '=', 'v2_user_roles.user_id')->where('v2_user_roles.user_id', $actor)->where('users.is_active', true)->value('v2_user_roles.role');
  38 |         if (! is_string($role) || ! in_array($role, ['admin', 'cashier'], true)) {
  39 |             abort(403);
  40 |         }
  41 | 
  42 |         return $role;
  43 |     }
  44 | 
  45 |     public function requireRole(int $actor, array $roles): void
  46 |     {
  47 |         if (! in_array($this->role($actor), $roles, true)) {
  48 |             abort(403);
  49 |         }
  50 |     }
```

Pagar arsip legacy — `app/Http/Middleware/V2Cutover.php:17-28`

```text
  17 |         if (! config('v2.enabled') || $request->is('v2', 'v2/*', 'login', 'logout', 'up')) {
  18 |             return $next($request);
  19 |         }
  20 |         if (! Auth::check()) {
  21 |             return $next($request);
  22 |         }
  23 |         $role = app(Operations::class)->role(Auth::id());
  24 |         if ($request->path() === '/') {
  25 |             return $next($request);
  26 |         }
  27 |         // Legacy remains an admin read-only archive after cutover.
  28 |         abort_unless($role === 'admin' && $request->isMethod('GET'), 403, 'Gunakan alur V2; arsip lama hanya dapat dibaca admin.');
```

### KS02 — Buka kas harian bersama oleh admin (sekali per tanggal)

Transaksi mengunci shift open/tanggal bisnis dan shift asal bila carry; asal wajib closed, counted_cash tersedia/tidak negatif dan belum pernah dibawa. carry paling banyak uang hitung asal; both wajib modal melebihi seluruh uang hitung asal serta kedua komponen positif. Insert v2_shifts open dengan is_shared=true, store_open_key=1, business_date=hari ini, open_user_id=admin, opening_cash/carry_amount/admin_amount dan carried_from_id. GET /v2/shifts/active memantau status kas harian untuk semua kasir. Tidak ada jurnal modal pada openShift.

Admin saja, sumber dan nominal modal — `app/Services/V2/Operations.php:52-67`

```text
  52 |     public function openShift(int $actor, string $cash, string $source, ?int $carryFrom = null): int
  53 |     {
  54 |         $this->requireRole($actor, ['admin']);
  55 |         if (! in_array($source, ['admin', 'carry', 'both'], true)) {
  56 |             $this->invalid('Sumber modal tidak valid.');
  57 |         }
  58 |         if (in_array($source, ['carry', 'both'], true) && $carryFrom === null) {
  59 |             $this->invalid('Shift asal wajib untuk sumber carry atau both.');
  60 |         }
  61 |         if ($source === 'admin' && $carryFrom !== null) {
  62 |             $this->invalid('Sumber admin tidak boleh membawa shift asal.');
  63 |         }
  64 |         $opening = $this->amount($cash, 2, 'Modal awal');
  65 |         if ($opening->isNegative()) {
  66 |             $this->invalid('Modal awal tidak boleh negatif.');
  67 |         }
```

Satu kas harian terbuka dan sekali per tanggal — `app/Services/V2/Operations.php:70-85`

```text
  70 |             return DB::transaction(function () use ($actor, $opening, $source, $carryFrom) {
  71 |                 $openedAt = now();
  72 |                 $businessDate = $openedAt->toDateString();
  73 |                 if (DB::table('v2_shifts')->where('status', 'open')->lockForUpdate()->exists()) {
  74 |                     $this->invalid('Masih ada kas harian yang terbuka; tutup catatan tanggal tersebut sebelum membuka hari berikutnya.');
  75 |                 }
  76 | 
  77 |                 if (DB::table('v2_shifts')->where(function ($query) use ($businessDate): void {
  78 |                     $query->where('business_date', $businessDate)->orWhereDate('opened_at', $businessDate);
  79 |                 })->lockForUpdate()->exists()) {
  80 |                     $this->invalid('Kas harian untuk tanggal ini sudah pernah dibuka; pembukaan ulang tidak diizinkan.');
  81 |                 }
  82 | 
  83 |                 $carry = Money::value('0');
  84 |                 $admin = $opening;
  85 | 
```

Simpan kas harian bersama terbuka — `app/Services/V2/Operations.php:122-137`

```text
 122 |                 return DB::table('v2_shifts')->insertGetId([
 123 |                     'user_id' => $actor,
 124 |                     'is_shared' => true,
 125 |                     'store_open_key' => 1,
 126 |                     'open_user_id' => $actor,
 127 |                     'source' => $source,
 128 |                     'opening_cash' => $this->str($opening),
 129 |                     'carry_amount' => $this->str($carry),
 130 |                     'admin_amount' => $this->str($admin),
 131 |                     'status' => 'open',
 132 |                     'business_date' => $businessDate,
 133 |                     'opened_at' => $openedAt,
 134 |                     'carried_from_id' => $carryFrom,
 135 |                     'created_at' => now(),
 136 |                     'updated_at' => now(),
 137 |                 ]);
```

### KS03 — Susun keranjang, pembayaran, rekening bank/jatuh tempo, lalu tinjau

Dialog pembayaran lalu tinjau tetap belum menyimpan. Konfirmasi final menetapkan __finalConfirmed dan requestSubmit; handler membentuk payload (termasuk bank_account_code, due_date, dan berkas transfer_proof bila ada) serta kunci idempotensi. Respons sukses menampilkan nomor/status/kembalian, tautan nota, mengganti kunci dan mengosongkan item; event v2:sale-saved mengubah dialog menjadi hasil. Validasi UI bukan pengganti server.

Target form checkout V2 — `resources/views/v2/pos-cashier.blade.php:17-34`

```text
  17 |     <div class="pos-cart-pane"><section class="card" aria-labelledby="h-checkout"><h2 id="h-checkout">Keranjang</h2>
  18 |         <form id="form-checkout" action="{{ route('v2.sales.store') }}" novalidate>
  19 |             <!-- Informasi Pembeli / Pelanggan -->
  20 |             <div class="pos-buyer-card">
  21 |                 <div class="pos-buyer-header">
  22 |                     <label for="pos-customer-quick" class="pos-buyer-title">
  23 |                         <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
  24 |                         <span>Nama Pembeli</span>
  25 |                         <span id="pos-buyer-req" class="pos-req-indicator" hidden>(wajib)</span>
  26 |                     </label>
  27 |                     <span id="pos-buyer-badge" class="pos-buyer-badge badge-general">Pelanggan Umum</span>
  28 |                 </div>
  29 |                 <div class="pos-buyer-select-wrapper">
  30 |                     <select id="pos-customer-quick" class="pos-customer-select" aria-label="Pilih pelanggan transaksi">
  31 |                         <option value="">Pelanggan Umum (Walk-in / Tunai)</option>
  32 |                     </select>
  33 |                 </div>
  34 |                 <div class="pos-buyer-meta">
```

Tinjau, konfirmasi, dan hasil — `resources/views/v2/pos-cashier-script.blade.php:179-190`

```text
 179 |  function renderReview(){const box=document.querySelector('#checkout-review');box.replaceChildren();const list=document.createElement('ul');list.className='plain';for(const row of rows()){const li=document.createElement('li');li.textContent=row.querySelector('.cart-product-title').textContent+' × '+row.querySelector('[data-name=quantity]').value+' · '+window.uiNumbers.get(row.querySelector('.item-subtotal'));list.append(li);}box.append(list);const cid=form.elements.customer_id.value;const custText=cid?form.elements.customer_id.selectedOptions[0].textContent.trim():'Pelanggan Umum (Walk-in)';const bankEl=form.elements.bank_account_code;const bankLine=bankEl&&!bankEl.disabled&&bankEl.selectedOptions[0]?[['Rekening bank',bankEl.selectedOptions[0].textContent.trim()]]:[];const dueEl=form.elements.due_date;const dueLine=dueEl&&!dueEl.disabled&&dueEl.value?[['Jatuh tempo',dueEl.value]]:[];const lines=[['Pembeli',custText],...(contractorOn()?[['Harga','Harga kontraktor']]:[]),['Total',D.money(total)],['Metode',form.elements.payment_method.selectedOptions[0].textContent],...bankLine,...dueLine,['Dibayar sekarang',D.money(D.scaled(paid.value||'0',2))],['Kembalian',window.uiNumbers.get(document.querySelector('#display-change'))]];if(form.elements.fulfillment.value==='delivery'){lines.splice(2,0,['Pengiriman','Dikirim ke '+(form.elements.destination.value.trim()||'-')+' · Ongkir '+D.money(D.scaled(document.querySelector('#delivery-fee').value||'0',2))]);}for(const [label,value]of lines){const p=document.createElement('p');p.className='review-line';const title=document.createElement('strong'),v=document.createElement('span');title.textContent=label;v.textContent=value;p.append(title,v);box.append(p);}}
 180 | document.querySelector('#review-payment').addEventListener('click',()=>{refresh();if(!valid)return;try{const tender=D.scaled(paid.value||'0',2),method=form.elements.payment_method.value;if(method==='cash'&&tender<total)throw Error('Nominal pembayaran kurang dari total.');if(method==='credit'&&tender>total)throw Error('Uang muka tidak boleh melebihi total.');if(method==='credit'&&!form.elements.customer_id.value)throw Error('Pilih pelanggan untuk transaksi kredit.');if(form.elements.fulfillment.value==='delivery'&&!form.elements.destination.value.trim())throw Error('Tujuan kirim wajib diisi.');renderReview();payment.close();review.showModal();confirm.focus({preventScroll:true});}catch(e){fail(e.message);}});
 181 | let sold=[];const payTitle=payment.querySelector('.popup-heading h2').textContent;
 182 | confirm.addEventListener('click',()=>{if(submitting||form.dataset.busy||saved)return;sold=rows().map(row=>({id:row.querySelector('[data-name=product_id]').value,qty:row.querySelector('[data-name=quantity]').value}));submitting=true;confirm.disabled=true;review.close();payment.showModal();for(const input of form.querySelectorAll('[inputmode=decimal]'))input.value=normalize(input.value);form.__finalConfirmed=true;form.requestSubmit(document.querySelector('#checkout-submit'));});
 183 | const busy=new MutationObserver(()=>{if(!form.dataset.busy&&submitting&&!saved){submitting=false;confirm.disabled=false;}});busy.observe(form,{attributes:true,attributeFilter:['data-busy']});
 184 | document.addEventListener('store-shift:changed',()=>{if(!submitting&&!saved)refresh();});
 185 |  form.addEventListener('v2:sale-saved',e=>{saved=true;submitting=false;try{localStorage.removeItem(cartKey);}catch{}payment.querySelector('.popup-heading h2').textContent='Transaksi Tersimpan — Periksa Sebelum Cetak';document.querySelector('#review-payment').hidden=true;payButton.disabled=true;document.querySelector('#cart-empty-row').hidden=false;window.uiNumbers.text(document.querySelector('#cart-item-count'),'0 jenis item');const link=document.querySelector('#checkout-result a');if(link)link.focus({preventScroll:true});const next=document.createElement('button');next.type='button';next.textContent='Penjualan berikutnya';next.addEventListener('click',()=>{document.querySelector('dialog.ur-dialog[open]')?.close();window.kasirNextSale();});document.querySelector('#checkout-result').append(document.createElement('br'),next);});
 186 | // Penjualan berikutnya tanpa memuat ulang: stok katalog dikurangi sesuai nota yang baru tersimpan (server sudah mengonfirmasi),
 187 | // keranjang dan form dikosongkan, lalu ringkasan/riwayat diperbarui lewat uiLive.
 188 | window.kasirNextSale=()=>{
 189 |  for(const line of sold){const e=prepared.find(x=>x.p.id===String(line.id));if(!e)continue;try{e.qty-=D.scaled(normalize(line.qty),4);e.p.stock_quantity=D.plain(e.qty,4);}catch{}}
 190 |  sold=[];saved=false;submitting=false;confirm.disabled=false;
```

Payload penjualan (termasuk bank dan jatuh tempo) — `resources/views/v2/workspace.blade.php:532-548`

```text
 532 |             var method = val(checkout, 'payment_method');
 533 |             var payload = {
 534 |                 items: items,
 535 |                 delivery_fee: val(checkout, 'delivery_fee'),
 536 |                 payment_method: method,
 537 |                 deposit_method: method === 'credit' ? val(checkout, 'deposit_method') : null,
 538 |                 bank_account_code: checkout.elements['bank_account_code'] && !checkout.elements['bank_account_code'].disabled && checkout.elements['bank_account_code'].value ? checkout.elements['bank_account_code'].value : null,
 539 |                 due_date: method === 'credit' && val(checkout, 'due_date') !== '' ? val(checkout, 'due_date') : null,
 540 |                 paid_amount: val(checkout, 'paid_amount'),
 541 |                 idempotency_key: val(checkout, 'idempotency_key'),
 542 |                 fulfillment: val(checkout, 'fulfillment'),
 543 |                 price_tier: val(checkout, 'price_tier') || 'retail'
 544 |             };
 545 |             var cid = val(checkout, 'customer_id');
 546 |             payload.customer_id = cid === '' ? null : cid;
 547 |             payload.destination = payload.fulfillment === 'delivery' ? val(checkout, 'destination') : null;
 548 |             var dt = document.getElementById('inv-disc-type').value;
```

### KS04 — Validasi server, bukti transfer, kunci kas harian, dan cegah checkout ganda

Di dalam satu transaksi, openShiftFor mengunci kas harian bersama aktif (is_shared=true, store_open_key=1, status=open) SEBELUM mencari replay. Kunci lama hanya dapat diulang aktor sama dengan request_hash sama; berbeda aktor 403 atau isi berbeda ditolak. Replay mengembalikan transaksi lama tanpa menulis lagi; bukti transfer yang berbeda hash pada payment yang sama ditolak. Produk duplikat/tidak ditemukan/arsip, jumlah tidak positif dan stok kurang ditolak.

Validasi kontrak checkout dan bukti transfer — `app/Http/Controllers/V2/WorkspaceController.php:493-522`

```text
 493 |     public function checkout(Request $request): JsonResponse
 494 |     {
 495 |         $this->operations->requireRole($request->user()->id, ['admin', 'cashier']);
 496 |         $data = $request->validate([
 497 |             'items' => ['required', 'array', 'min:1', 'max:100'],
 498 |             'items.*.product_id' => ['required', 'integer', 'min:1'],
 499 |             'items.*.quantity' => ['required', 'string', 'max:30'],
 500 |             'items.*.discount' => ['sometimes', 'array:type,value'],
 501 |             'items.*.discount.type' => ['required_with:items.*.discount', Rule::in(['amount', 'percent'])],
 502 |             'items.*.discount.value' => ['required_with:items.*.discount', 'string', 'max:30'],
 503 |             'discount' => ['sometimes', 'array:type,value'],
 504 |             'discount.type' => ['required_with:discount', Rule::in(['amount', 'percent'])],
 505 |             'discount.value' => ['required_with:discount', 'string', 'max:30'],
 506 |             'delivery_fee' => ['required', 'string', 'max:30'],
 507 |             'customer_id' => ['nullable', 'integer', 'min:1'],
 508 |             'payment_method' => ['required', Rule::in(['cash', 'transfer', 'credit'])],
 509 |             'deposit_method' => ['nullable', Rule::in(['cash', 'transfer'])],
 510 |             'bank_account_code' => ['nullable', 'string', 'max:20'],
 511 |             'due_date' => ['nullable', 'date_format:Y-m-d'],
 512 |             'paid_amount' => ['required', 'string', 'max:30'],
 513 |             'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
 514 |             'fulfillment' => ['required', Rule::in(['pickup', 'delivery'])],
 515 |             'destination' => ['nullable', 'required_if:fulfillment,delivery', 'string', 'max:255'],
 516 |             'price_tier' => ['nullable', Rule::in(['retail', 'contractor'])],
 517 |         ]);
 518 | 
 519 |         $isTransfer = $data['payment_method'] === 'transfer' || ($data['payment_method'] === 'credit' && ($data['deposit_method'] ?? '') === 'transfer');
 520 |         $needsProof = $isTransfer && ! preg_match('/^0+(?:\.0+)?$/', trim($data['paid_amount']));
 521 |         $photo = $this->validateTransferProof($request, $needsProof);
 522 | 
```

Kunci kas harian sebelum pemeriksaan replay — `app/Services/V2/Operations.php:348-360`

```text
 348 |         return DB::transaction(function () use ($actor, $data, $key, $method, $fulfillment, $destination, $items, $deliveryFee, $customerId, $vehicleId, $driverName, $tier, $dueDate) {
 349 |             $shift = $this->openShiftFor($actor, true);
 350 |             $requestHash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
 351 |             $existing = DB::table('v2_sales')->where('idempotency_key', $key)->first();
 352 |             if ($existing) {
 353 |                 abort_unless((int) $existing->user_id === $actor, 403);
 354 |                 if ($existing->request_hash !== $requestHash) {
 355 |                     $this->invalid('Kunci transaksi sudah dipakai untuk isi berbeda.');
 356 |                 }
 357 | 
 358 |                 return $this->replay((int) $existing->id);
 359 |             }
 360 | 
```

Kas harian bersama wajib terbuka — `app/Services/V2/Operations.php:1032-1050`

```text
1032 |     public function activeStoreShift(bool $lock = false): ?object
1033 |     {
1034 |         $query = DB::table('v2_shifts')->where('is_shared', true)->where('store_open_key', 1)->where('status', 'open');
1035 |         if ($lock) {
1036 |             $query->lockForUpdate();
1037 |         }
1038 | 
1039 |         return $query->first();
1040 |     }
1041 | 
1042 |     private function openShiftFor(int $actor, bool $lock): object
1043 |     {
1044 |         $shift = $this->activeStoreShift($lock);
1045 |         if (! $shift) {
1046 |             $this->invalid('Shift toko belum dibuka admin.');
1047 |         }
1048 | 
1049 |         return $shift;
1050 |     }
```

### KS05 — Posting nota, stok, HPP, piutang dan pendapatan secara atomik

Dalam transaksi checkout yang sama dibuat INV v2_sales dan snapshot v2_sale_items, stok products berkurang dan stock_movements reference_type=v2_sale negatif. Semua metode membuat AR v2_invoices dengan amount total, paid sesuai cabang pembayaran, dan due_date opsional (kredit); invoice paid jika lunas, selain itu open. Ledger v2_sale: DR1103 total; CR4101 barang net bila positif; CR4102 ongkir bila positif; DR5101/CR1301 HPP bila positif. Akun aktif dan jurnal seimbang wajib; kegagalan membatalkan keseluruhan checkout.

Kurangi stok dan catat mutasi penjualan — `app/Services/V2/Operations.php:527-542`

```text
 527 |                 $balance = $row['stock']->minus($row['quantity'])->toScale(4, RoundingMode::HalfUp);
 528 |                 DB::table('products')->where('id', $row['product_id'])->update([
 529 |                     'stock_quantity' => $this->str($balance, 4),
 530 |                     'updated_at' => now(),
 531 |                 ]);
 532 |                 DB::table('stock_movements')->insert([
 533 |                     'product_id' => $row['product_id'],
 534 |                     'reference_type' => 'v2_sale',
 535 |                     'reference_id' => $saleId,
 536 |                     'quantity_delta' => $this->str($row['quantity']->negated(), 4),
 537 |                     'balance_after' => $this->str($balance, 4),
 538 |                     'notes' => 'Penjualan '.$number,
 539 |                     'created_at' => now(),
 540 |                     'updated_at' => now(),
 541 |                 ]);
 542 |             }
```

Buat AR dengan jatuh tempo opsional — `app/Services/V2/Operations.php:544-558`

```text
 544 |             $invoiceId = DB::table('v2_invoices')->insertGetId([
 545 |                 'direction' => 'receivable',
 546 |                 'party_id' => $customerId,
 547 |                 'sale_id' => $saleId,
 548 |                 'number' => 'TMP-'.bin2hex(random_bytes(8)),
 549 |                 'amount' => $this->str($total),
 550 |                 'paid' => $this->str($paid),
 551 |                 'due_date' => $dueDate,
 552 |                 'status' => $paid->isGreaterThanOrEqualTo($total) ? 'paid' : 'open',
 553 |                 'created_at' => now(),
 554 |                 'updated_at' => now(),
 555 |             ]);
 556 |             $invoiceNumber = 'AR-'.now()->format('Ymd').'-'.str_pad((string) $invoiceId, 5, '0', STR_PAD_LEFT);
 557 |             DB::table('v2_invoices')->where('id', $invoiceId)->update(['number' => $invoiceNumber, 'updated_at' => now()]);
 558 | 
```

Jurnal pendapatan dan HPP — `app/Services/V2/Operations.php:559-573`

```text
 559 |             $goodsNet = $goodsBase->minus($invoiceDiscount)->toScale(2, RoundingMode::HalfUp);
 560 |             $saleLines = [
 561 |                 ['account_code' => self::AR, 'debit' => $this->str($total), 'credit' => '0.00'],
 562 |             ];
 563 |             if ($goodsNet->isPositive()) {
 564 |                 $saleLines[] = ['account_code' => self::GOODS_REVENUE, 'debit' => '0.00', 'credit' => $this->str($goodsNet)];
 565 |             }
 566 |             if ($deliveryFee->isPositive()) {
 567 |                 $saleLines[] = ['account_code' => self::DELIVERY_REVENUE, 'debit' => '0.00', 'credit' => $this->str($deliveryFee)];
 568 |             }
 569 |             if ($cogs->isPositive()) {
 570 |                 $saleLines[] = ['account_code' => self::COGS, 'debit' => $this->str($cogs), 'credit' => '0.00'];
 571 |                 $saleLines[] = ['account_code' => self::INVENTORY, 'debit' => '0.00', 'credit' => $this->str($cogs)];
 572 |             }
 573 |             Ledger::post('v2_sale', $saleId, 'Penjualan '.$number, $saleLines);
```

### KS06 — Cabang tunai: lunas, kembalian, dan masuk laci kas harian

paid=total, change=tendered−total, status sale paid dan invoice paid. Buat pembayaran cash posted/approved_by aktor; jurnal DR1101/CR1103 sebesar total, bukan uang diserahkan. Mutasi laci in kind sale reference v2_sale sebesar paid neto pada kas harian bersama, sehingga kembalian tidak dihitung sebagai penerimaan tambahan. Nota/stok/HPP/pendapatan tetap melalui posting bersama KS05.

Uang tunai cukup dan kembalian — `app/Services/V2/Operations.php:449-459`

```text
 449 |             if ($method === 'cash') {
 450 |                 if (! array_key_exists('paid_amount', $data)) {
 451 |                     $this->invalid('Uang tunai diterima wajib diisi.');
 452 |                 }
 453 |                 $tendered = $this->amount($data['paid_amount'], 2, 'Uang diterima');
 454 |                 if ($tendered->isLessThan($total)) {
 455 |                     $this->invalid('Uang tunai kurang dari total.');
 456 |                 }
 457 |                 $paid = $total;
 458 |                 $change = $tendered->minus($total);
 459 |             } else {
```

Pembayaran tunai langsung posted — `app/Services/V2/Operations.php:575-590`

```text
 575 |             if ($paid->isPositive()) {
 576 |                 $paymentId = DB::table('v2_payments')->insertGetId([
 577 |                     'invoice_id' => $invoiceId,
 578 |                     'shift_id' => (int) $shift->id,
 579 |                     'amount' => $this->str($paid),
 580 |                     'method' => 'cash',
 581 |                     'status' => 'posted',
 582 |                     'requested_by' => $actor,
 583 |                     'approved_by' => $actor,
 584 |                     'reference' => null,
 585 |                     'idempotency_key' => $key.':payment',
 586 |                     'request_hash' => $requestHash,
 587 |                     'journal_entry_id' => null,
 588 |                     'created_at' => now(),
 589 |                     'updated_at' => now(),
 590 |                 ]);
```

Jurnal kas/piutang dan mutasi laci neto — `app/Services/V2/Operations.php:591-607`

```text
 591 |                 $entryId = Ledger::post('v2_payment', $paymentId, 'Penerimaan tunai '.$number, [
 592 |                     ['account_code' => self::CASH, 'debit' => $this->str($paid), 'credit' => '0.00'],
 593 |                     ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($paid)],
 594 |                 ]);
 595 |                 DB::table('v2_payments')->where('id', $paymentId)->update(['journal_entry_id' => $entryId, 'updated_at' => now()]);
 596 | 
 597 |                 DB::table('v2_shift_movements')->insert([
 598 |                     'shift_id' => (int) $shift->id,
 599 |                     'direction' => 'in',
 600 |                     'kind' => $method === 'cash' ? 'sale' : 'collection',
 601 |                     'amount' => $this->str($paid),
 602 |                     'reference_type' => $method === 'cash' ? 'v2_sale' : 'v2_payment',
 603 |                     'reference_id' => $method === 'cash' ? $saleId : $paymentId,
 604 |                     'note' => 'Penjualan '.$number,
 605 |                     'created_at' => now(),
 606 |                     'updated_at' => now(),
 607 |                 ]);
```

### KS07 — Cabang transfer: pilih rekening bank, bukti foto, dan verifikasi tertunda

paid tetap nol sehingga sale unpaid dan AR open. Jika tendered positif dibuat payment transfer pending dengan bank_account_code terpilih dan bukti foto tersimpan di v2_payment_proofs (disk lokal privat), shift_id null, approved_by/journal_entry_id null; belum menambah saldo bank, belum mengurangi AR dan tidak ada mutasi laci. Jika nominal nol, tidak dibuat payment sama sekali. Stok/HPP/pendapatan tetap diposting saat checkout; lanjut KS11 hanya jika payment pending benar-benar ada.

Transfer nol atau total, belum diakui paid — `app/Services/V2/Operations.php:464-469`

```text
 464 |                 if ($method === 'transfer') {
 465 |                     if (! $tendered->isZero() && ! $tendered->isEqualTo($total)) {
 466 |                         $this->invalid('Transfer harus nol atau tepat sebesar total.');
 467 |                     }
 468 |                     $paid = Money::value('0');
 469 |                 } else {
```

Transfer pending dengan rekening bank tujuan — `app/Services/V2/Operations.php:608-629`

```text
 608 |             } elseif ($tendered->isPositive() && ($method === 'transfer' || $depositMethod === 'transfer')) {
 609 |                 $bankCode = app(BankAccounts::class)->resolve(isset($data['bank_account_code']) ? (string) $data['bank_account_code'] : null);
 610 |                 $paymentRow = [
 611 |                     'invoice_id' => $invoiceId,
 612 |                     'shift_id' => null,
 613 |                     'amount' => $this->str($tendered),
 614 |                     'method' => 'transfer',
 615 |                     'status' => 'pending',
 616 |                     'requested_by' => $actor,
 617 |                     'approved_by' => null,
 618 |                     'reference' => null,
 619 |                     'idempotency_key' => $key.':payment',
 620 |                     'request_hash' => $requestHash,
 621 |                     'journal_entry_id' => null,
 622 |                     'created_at' => now(),
 623 |                     'updated_at' => now(),
 624 |                 ];
 625 |                 if (Schema::hasColumn('v2_payments', 'bank_account_code')) {
 626 |                     $paymentRow['bank_account_code'] = $bankCode;
 627 |                 }
 628 |                 DB::table('v2_payments')->insert($paymentRow);
 629 |             }
```

Status sale unpaid/partial/paid — `app/Services/V2/Operations.php:1072-1079`

```text
1072 |     private function saleStatus(BigDecimal $paid, BigDecimal $total): string
1073 |     {
1074 |         if ($paid->isZero()) {
1075 |             return 'unpaid';
1076 |         }
1077 | 
1078 |         return $paid->isGreaterThanOrEqualTo($total) ? 'paid' : 'partial';
1079 |     }
```

### KS08 — Cabang kredit: tanpa DP, DP tunai, atau DP transfer + jatuh tempo opsional

Tanpa DP: paid nol, sale unpaid, AR open (dengan due_date jika diisi), tanpa payment. DP cash: paid langsung sebesar DP, payment cash posted DR1101/CR1103 dan mutasi laci in kind collection reference v2_payment pada kas harian bersama; sale partial atau paid bila DP=total. DP transfer: paid tetap nol; buat transfer pending beserta bank_account_code dan bukti foto sebesar DP, tanpa bank/laci/penurunan AR sebelum verifikasi KS11. Label metode sale tetap credit.

Validasi DP dan nilai paid kredit — `app/Services/V2/Operations.php:470-483`

```text
 470 |                     if ($tendered->isGreaterThan($total)) {
 471 |                         $this->invalid('Uang muka melebihi total.');
 472 |                     }
 473 |                     if ($tendered->isPositive()) {
 474 |                         $depositMethod = (string) ($data['deposit_method'] ?? '');
 475 |                         if (! in_array($depositMethod, ['cash', 'transfer'], true)) {
 476 |                             $this->invalid('Metode uang muka wajib cash atau transfer.');
 477 |                         }
 478 |                         $paid = $depositMethod === 'cash' ? $tendered : Money::value('0');
 479 |                     } else {
 480 |                         $paid = Money::value('0');
 481 |                     }
 482 |                 }
 483 |             }
```

DP tunai sebagai collection laci — `app/Services/V2/Operations.php:591-607`

```text
 591 |                 $entryId = Ledger::post('v2_payment', $paymentId, 'Penerimaan tunai '.$number, [
 592 |                     ['account_code' => self::CASH, 'debit' => $this->str($paid), 'credit' => '0.00'],
 593 |                     ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($paid)],
 594 |                 ]);
 595 |                 DB::table('v2_payments')->where('id', $paymentId)->update(['journal_entry_id' => $entryId, 'updated_at' => now()]);
 596 | 
 597 |                 DB::table('v2_shift_movements')->insert([
 598 |                     'shift_id' => (int) $shift->id,
 599 |                     'direction' => 'in',
 600 |                     'kind' => $method === 'cash' ? 'sale' : 'collection',
 601 |                     'amount' => $this->str($paid),
 602 |                     'reference_type' => $method === 'cash' ? 'v2_sale' : 'v2_payment',
 603 |                     'reference_id' => $method === 'cash' ? $saleId : $paymentId,
 604 |                     'note' => 'Penjualan '.$number,
 605 |                     'created_at' => now(),
 606 |                     'updated_at' => now(),
 607 |                 ]);
```

DP transfer tetap pending dengan rekening bank — `app/Services/V2/Operations.php:608-629`

```text
 608 |             } elseif ($tendered->isPositive() && ($method === 'transfer' || $depositMethod === 'transfer')) {
 609 |                 $bankCode = app(BankAccounts::class)->resolve(isset($data['bank_account_code']) ? (string) $data['bank_account_code'] : null);
 610 |                 $paymentRow = [
 611 |                     'invoice_id' => $invoiceId,
 612 |                     'shift_id' => null,
 613 |                     'amount' => $this->str($tendered),
 614 |                     'method' => 'transfer',
 615 |                     'status' => 'pending',
 616 |                     'requested_by' => $actor,
 617 |                     'approved_by' => null,
 618 |                     'reference' => null,
 619 |                     'idempotency_key' => $key.':payment',
 620 |                     'request_hash' => $requestHash,
 621 |                     'journal_entry_id' => null,
 622 |                     'created_at' => now(),
 623 |                     'updated_at' => now(),
 624 |                 ];
 625 |                 if (Schema::hasColumn('v2_payments', 'bank_account_code')) {
 626 |                     $paymentRow['bank_account_code'] = $bankCode;
 627 |                 }
 628 |                 DB::table('v2_payments')->insert($paymentRow);
 629 |             }
```

### KS09 — Pilihan kirim membuat surat jalan prepared, bukan otomatis dispatch

Buat v2_deliveries terkait sale_id, nomor SJ, destination, status prepared; pickup tidak membuat delivery. Checkout mengembalikan sale_id, invoice_id, total, paid, change, status dan delivery_number. Surat jalan diserahkan ke alur downstream POST /v2/deliveries/{id}/dispatch, tidak dibuktikan sebagai prasyarat tutup kas harian atau otomatis terkirim/lunas.

Surat jalan hanya disiapkan untuk delivery — `app/Services/V2/Operations.php:631-646`

```text
 631 |             $deliveryNumber = null;
 632 |             if ($fulfillment === 'delivery') {
 633 |                 $deliveryId = DB::table('v2_deliveries')->insertGetId([
 634 |                     'sale_id' => $saleId,
 635 |                     'number' => 'TMP-'.bin2hex(random_bytes(8)),
 636 |                     'vehicle_id' => $vehicleId,
 637 |                     'driver_name' => $driverName,
 638 |                     'destination' => $destination,
 639 |                     'status' => 'prepared',
 640 |                     'created_at' => now(),
 641 |                     'updated_at' => now(),
 642 |                 ]);
 643 |                 $deliveryNumber = 'SJ-'.now()->format('Ymd').'-'.str_pad((string) $deliveryId, 5, '0', STR_PAD_LEFT);
 644 |                 DB::table('v2_deliveries')->where('id', $deliveryId)->update(['number' => $deliveryNumber, 'updated_at' => now()]);
 645 |             }
 646 | 
```

Hasil transaksi — `app/Services/V2/Operations.php:647-657`

```text
 647 |             return [
 648 |                 'sale_id' => $saleId,
 649 |                 'number' => $number,
 650 |                 'invoice_id' => $invoiceId,
 651 |                 'invoice_number' => $invoiceNumber,
 652 |                 'total' => $this->str($total),
 653 |                 'paid' => $this->str($paid),
 654 |                 'change' => $this->str($change),
 655 |                 'status' => $status,
 656 |                 'delivery_number' => $deliveryNumber,
 657 |             ];
```

Pointer endpoint dokumen/dispatch downstream — `routes/v2.php:31-35`

```text
  31 |     Route::get('/documents/{id}/{kind}', [WorkspaceController::class, 'document'])->whereNumber('id')->whereIn('kind', ['receipt', 'delivery'])->name('documents');
  32 |     Route::post('/opening-balances', [WorkspaceController::class, 'opening'])->name('openings.store');
  33 |     Route::post('/deliveries/{id}/dispatch', [WorkspaceController::class, 'dispatch'])->whereNumber('id')->name('deliveries.dispatch');
  34 |     Route::post('/deliveries/{id}/receive', [WorkspaceController::class, 'receiveDelivery'])->whereNumber('id')->name('deliveries.receive');
  35 |     Route::post('/driver-advances', [WorkspaceController::class, 'advance'])->name('driver-advances.store');
```

### KS10 — Lanjutan piutang: penerimaan tunai atau pengajuan transfer berbukti

Transfer membuat payment pending shift_id null dengan bank_account_code dan bukti foto di v2_payment_proofs tanpa mengubah AR/sale/laci hingga KS11. Cash wajib kas harian bersama aktif terbuka: payment posted, DR1101/CR1103, mutasi in collection pada kas harian aktif, invoice.paid bertambah dan sale partial/paid.

Pagar AR, entri dobel dan sisa tagihan — `app/Services/V2/Operations.php:700-716`

```text
 700 |             if (! in_array($invoice->direction, ['receivable', 'payable'], true)) {
 701 |                 $this->invalid('Arah tagihan tidak valid.');
 702 |             }
 703 |             if ($invoice->direction === 'receivable' && $method === 'main') {
 704 |                 $this->invalid('Kas Utama hanya untuk pembayaran ke supplier.');
 705 |             }
 706 |             if ($seenPaymentId !== null && ! $confirmed) {
 707 |                 $this->guardNewerPayments($invoiceId, $seenPaymentId);
 708 |             }
 709 | 
 710 |             $remaining = Money::value((string) $invoice->amount, 2)->minus(Money::value((string) $invoice->paid, 2));
 711 |             if (! $remaining->isPositive()) {
 712 |                 $this->invalid('Tagihan sudah lunas.');
 713 |             }
 714 |             if ($value->isGreaterThan($remaining)) {
 715 |                 $this->invalid('Nominal melebihi sisa tagihan.');
 716 |             }
```

Transfer lanjutan tetap pending dengan rekening bank — `app/Services/V2/Operations.php:753-774`

```text
 753 |             if ($method === 'transfer') {
 754 |                 $paymentRow = [
 755 |                     'invoice_id' => $invoiceId,
 756 |                     'shift_id' => null,
 757 |                     'amount' => $this->str($value),
 758 |                     'method' => 'transfer',
 759 |                     'status' => 'pending',
 760 |                     'requested_by' => $actor,
 761 |                     'approved_by' => null,
 762 |                     'reference' => $reference,
 763 |                     'idempotency_key' => $key,
 764 |                     'request_hash' => $requestHash,
 765 |                     'journal_entry_id' => null,
 766 |                     'created_at' => now(),
 767 |                     'updated_at' => now(),
 768 |                 ];
 769 |                 if ($hasBankCol) {
 770 |                     $paymentRow['bank_account_code'] = $resolvedBank;
 771 |                 }
 772 | 
 773 |                 return DB::table('v2_payments')->insertGetId($paymentRow);
 774 |             }
```

Jurnal dan mutasi collection tunai — `app/Services/V2/Operations.php:793-815`

```text
 793 |             $entryId = Ledger::post('v2_payment', $paymentId, 'Penerimaan '.$invoice->number, [
 794 |                 ['account_code' => self::CASH, 'debit' => $this->str($value), 'credit' => '0.00'],
 795 |                 ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($value)],
 796 |             ]);
 797 |             DB::table('v2_payments')->where('id', $paymentId)->update(['journal_entry_id' => $entryId, 'updated_at' => now()]);
 798 | 
 799 |             DB::table('v2_shift_movements')->insert([
 800 |                 'shift_id' => (int) $shift->id,
 801 |                 'direction' => 'in',
 802 |                 'kind' => 'collection',
 803 |                 'amount' => $this->str($value),
 804 |                 'reference_type' => 'v2_payment',
 805 |                 'reference_id' => $paymentId,
 806 |                 'note' => 'Penerimaan '.$invoice->number,
 807 |                 'created_at' => now(),
 808 |                 'updated_at' => now(),
 809 |             ]);
 810 | 
 811 |             DB::table('v2_invoices')->where('id', $invoiceId)->update([
 812 |                 'paid' => $this->str(Money::value((string) $invoice->paid, 2)->plus($value)),
 813 |                 'status' => $value->isEqualTo($remaining) ? 'paid' : 'open',
 814 |                 'updated_at' => now(),
 815 |             ]);
```

### KS11 — Verifikasi (dengan opsi koreksi rekening bank) atau tolak transfer piutang

Approve mengunci invoice/payment, posted replay tanpa menulis, status selain pending/proposed ditolak; nominal positif dan tidak melebihi sisa. Bila admin mengirim bank_account_code, kode rekening divalidasi lewat BankAccounts::resolve dan diperbarui pada v2_payments sebelum posting. Untuk receivable hanya transfer: jurnal DR rekening bank terpilih (bawaan 1102)/CR 1103, payment posted + approved_by/journal_entry_id, invoice.paid bertambah dan sale partial/paid. Reject menandai rejected beserta alasan/audit tanpa membalik invoice/stok.

Koreksi rekening bank opsional dan verifikasi transfer AR — `app/Services/V2/Operations.php:856-880`

```text
 856 |             $overrideBank = null;
 857 |             if ($payment->method === 'transfer' && $bankAccountCode !== null && trim($bankAccountCode) !== '') {
 858 |                 $overrideBank = app(BankAccounts::class)->resolve($bankAccountCode);
 859 |                 $payment->bank_account_code = $overrideBank;
 860 |                 if (Schema::hasColumn('v2_payments', 'bank_account_code')) {
 861 |                     DB::table('v2_payments')->where('id', $paymentId)->update([
 862 |                         'bank_account_code' => $overrideBank,
 863 |                         'updated_at' => now(),
 864 |                     ]);
 865 |                 }
 866 |             }
 867 | 
 868 |             $entryId = null;
 869 |             if ($invoice->direction === 'receivable') {
 870 |                 if ($payment->method !== 'transfer') {
 871 |                     $this->invalid('Hanya transfer yang menunggu verifikasi bank.');
 872 |                 }
 873 |                 $bankCode = trim((string) ($payment->bank_account_code ?? '')) ?: self::BANK;
 874 |                 if (! app(BankAccounts::class)->isBankAccount($bankCode, true)) {
 875 |                     $this->invalid('Rekening bank tujuan sudah tidak aktif di Bagan Akun.');
 876 |                 }
 877 |                 $entryId = Ledger::post('v2_payment', $paymentId, 'Verifikasi transfer '.$invoice->number, [
 878 |                     ['account_code' => $bankCode, 'debit' => $this->str($amount), 'credit' => '0.00'],
 879 |                     ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($amount)],
 880 |                 ]);
```

Posting payment dan pembaruan invoice/sale — `app/Services/V2/Operations.php:889-903`

```text
 889 |             DB::table('v2_payments')->where('id', $paymentId)->update([
 890 |                 'status' => 'posted',
 891 |                 'approved_by' => $actor,
 892 |                 'journal_entry_id' => $entryId,
 893 |                 'updated_at' => now(),
 894 |             ]);
 895 |             DB::table('v2_invoices')->where('id', $invoice->id)->update([
 896 |                 'paid' => $this->str(Money::value((string) $invoice->paid, 2)->plus($amount)),
 897 |                 'status' => $amount->isEqualTo($remaining) ? 'paid' : 'open',
 898 |                 'updated_at' => now(),
 899 |             ]);
 900 | 
 901 |             if ($invoice->sale_id !== null) {
 902 |                 DB::table('v2_sales')->where('id', $invoice->sale_id)->update(['status' => $this->saleStatus(Money::value((string) $invoice->paid)->plus($amount), Money::value((string) $invoice->amount)), 'updated_at' => now()]);
 903 |             }
```

Penolakan hanya mengubah payment — `app/Services/V2/Operations.php:275-286`

```text
 275 |             if (! in_array($payment->status, ['pending', 'proposed'], true)) {
 276 |                 $this->invalid('Hanya pembayaran pending atau proposed yang dapat ditolak.');
 277 |             }
 278 | 
 279 |             DB::table('v2_payments')->where('id', $paymentId)->update([
 280 |                 'status' => 'rejected',
 281 |                 'resolution_note' => $note,
 282 |                 'resolved_by' => $actor,
 283 |                 'resolved_at' => now(),
 284 |                 'updated_at' => now(),
 285 |             ]);
 286 |         });
```

### KS12 — Ambil rekap penutupan dari kas harian yang masih open

Read-only: hitung jumlah/total nota v2_sales.shift_id (dibatasi user_id untuk kasir, seluruh kasir untuk admin) dan kelompok payment_method cash/transfer/credit. Hitung laci bersama dari opening_cash + seluruh movement in − movement out; rincian sale/collection/other in dan supplier/other out. Hanya payment method cash status proposed shift_id yang sama dihitung sebagai pending_drawer_payments. Kembalikan scope store/shift, sales_scope, serta fingerprint.

Otorisasi rekap dan sumber penjualan kas harian — `app/Services/V2/ShiftClosingSummary.php:19-38`

```text
  19 |     public function build(int $actor, int $shiftId): array
  20 |     {
  21 |         $role = $this->operations->role($actor);
  22 |         abort_unless(in_array($role, ['admin', 'cashier'], true), 403);
  23 | 
  24 |         return DB::transaction(function () use ($actor, $role, $shiftId): array {
  25 |             $shift = DB::table('v2_shifts')->where('id', $shiftId)->lockForUpdate()->first();
  26 |             abort_unless($shift, 404);
  27 |             if ($role !== 'admin' && ! $shift->is_shared && (int) $shift->user_id !== $actor) {
  28 |                 abort(403);
  29 |             }
  30 |             if ($shift->status !== 'open') {
  31 |                 throw ValidationException::withMessages(['shift' => 'Rekap hanya tersedia untuk shift yang masih terbuka.']);
  32 |             }
  33 | 
  34 |             $cashier = (string) DB::table('users')->where('id', $shift->user_id)->value('name');
  35 | 
  36 |             $sales = DB::table('v2_sales')->where('shift_id', $shiftId)
  37 |                 ->when($role !== 'admin', fn ($query) => $query->where('user_id', $actor))
  38 |                 ->get(['id', 'payment_method', 'total']);
```

Kelompok total menurut metode, bukan uang diterima — `app/Services/V2/ShiftClosingSummary.php:44-54`

```text
  44 |             foreach ($sales as $sale) {
  45 |                 $salesCount++;
  46 |                 $total = Money::value((string) $sale->total, 2);
  47 |                 $salesTotal = $salesTotal->plus($total);
  48 |                 match ($sale->payment_method) {
  49 |                     'cash' => $salesCash = $salesCash->plus($total),
  50 |                     'transfer' => $salesTransfer = $salesTransfer->plus($total),
  51 |                     'credit' => $salesCredit = $salesCredit->plus($total),
  52 |                     default => null,
  53 |                 };
  54 |             }
```

Expected, pending cash, scope dan fingerprint — `app/Services/V2/ShiftClosingSummary.php:85-100`

```text
  85 |             $opening = Money::value((string) $shift->opening_cash, 2);
  86 |             $expected = $opening->plus($cashIn)->minus($cashOut);
  87 | 
  88 |             $pending = DB::table('v2_payments')->where('shift_id', $shiftId)->where('method', 'cash')->where('status', 'proposed')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
  89 |             $fingerprint = $this->operations->closingFingerprint($shiftId);
  90 | 
  91 |             return [
  92 |                 'shift_id' => $shiftId,
  93 |                 'cashier' => $cashier,
  94 |                 'opened_by' => $cashier,
  95 |                 'is_shared' => (bool) $shift->is_shared,
  96 |                 'opened_at' => (string) $shift->opened_at,
  97 |                 'scope' => $shift->is_shared ? 'store' : 'shift',
  98 |                 'sales_scope' => $role === 'admin' ? 'all' : 'own',
  99 |                 'sales' => [
 100 |                     'count' => $salesCount,
```

### KS13 — Bandingkan uang fisik laci dengan perkiraan rekap

UI menampilkan total nota per metode dan kas awal/masuk/keluar/seharusnya; pending tunai diperlihatkan sebagai peringatan. BigInt menghitung sen: counted−expected; tampil Cocok, Selisih lebih atau kurang. Saat form dikirim POST /v2/shifts/{id}/close membawa counted dan fingerprint atau null; UI tetap mengizinkan tutup ketika fetch rekap gagal.

Simpan fingerprint/expected dan rincian penjualan — `resources/views/v2/workspace.blade.php:670-684`

```text
 670 |     function renderRecap(form, data) {
 671 |         const box = form.querySelector('[data-close-recap]');
 672 |         box.replaceChildren();
 673 |         form._fingerprint = data.fingerprint;
 674 |         form._expected = data.cash.expected;
 675 | 
 676 |         const sales = document.createElement('dl');
 677 |         sales.className = 'recap-sales';
 678 |         sales.append(
 679 |             dlRow('Penjualan (' + data.sales.count + ' nota)', fmt(data.sales.total)),
 680 |             dlRow('— tunai', fmt(data.sales.cash)),
 681 |             dlRow('— transfer', fmt(data.sales.transfer)),
 682 |             dlRow('— kredit', fmt(data.sales.credit)),
 683 |         );
 684 | 
```

Banding uang fisik menggunakan sen BigInt — `resources/views/v2/workspace.blade.php:702-714`

```text
 702 |         const counted = form.elements.counted;
 703 |         const match = form.querySelector('[data-close-match]');
 704 |         const recalc = () => {
 705 |             const raw = counted.value;
 706 |             if (!raw) { match.hidden = true; return; }
 707 |             match.hidden = false;
 708 |             const diff = cents(raw) - cents(form._expected);
 709 |             if (diff === 0n) { match.className = 'close-match ok'; match.textContent = 'Cocok dengan perkiraan ' + fmt(form._expected) + '.'; }
 710 |             else { match.className = 'close-match warn'; match.textContent = 'Selisih ' + (diff > 0n ? 'lebih ' : 'kurang ') + fmtCents(diff < 0n ? -diff : diff) + ' dari perkiraan.'; }
 711 |         };
 712 |         counted.addEventListener('input', recalc);
 713 |         recalc();
 714 |     }
```

Payload penutupan kas harian — `resources/views/v2/workspace.blade.php:457-462`

```text
 457 |     document.addEventListener('submit', function (ev) {
 458 |         var form = ev.target.closest && ev.target.closest('.form-close');
 459 |         if (!form) { return; }
 460 |         ev.preventDefault();
 461 |         post(form, form.action, { counted: val(form, 'counted'), fingerprint: form._fingerprint || null }, true).then(function (data) { if (data) { liveSaved(form); } });
 462 |     });
```

### KS14 — Periksa otorisasi admin dan rekap kedaluwarsa di bawah kunci shift

closeShift mengunci shift lalu membandingkan hash jika fingerprint diberikan. Hash memuat status/modal, mutasi laci berurutan, id/metode/total sale, dan id pengajuan cash proposed pada shift. Perubahan snapshot menghasilkan penolakan “Rekap sudah berubah ... tutup ulang” tanpa menutup. Fingerprint opsional; closeShift tetap menghitung expected terbaru sendiri.

Admin saja, nominal dan token opsional — `app/Services/V2/Operations.php:144-154`

```text
 144 |     public function closeShift(int $actor, int $shiftId, string $counted, ?string $fingerprint = null): array
 145 |     {
 146 |         $this->requireRole($actor, ['admin']);
 147 |         $count = $this->amount($counted, 2, 'Uang hitung');
 148 |         if ($count->isNegative()) {
 149 |             $this->invalid('Uang hitung tidak boleh negatif.');
 150 |         }
 151 |         if ($fingerprint !== null && ! preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
 152 |             $this->invalid('Tanda rekap tidak valid.');
 153 |         }
 154 | 
```

Kunci dan stale guard — `app/Services/V2/Operations.php:155-168`

```text
 155 |         return DB::transaction(function () use ($actor, $shiftId, $count, $fingerprint) {
 156 |             $shift = DB::table('v2_shifts')->where('id', $shiftId)->lockForUpdate()->first();
 157 |             if (! $shift) {
 158 |                 $this->invalid('Shift tidak ditemukan.');
 159 |             }
 160 |             if ($shift->status !== 'open') {
 161 |                 $this->invalid('Shift tidak dalam status terbuka.');
 162 |             }
 163 |             if ($this->role($actor) !== 'admin' && (int) $shift->user_id !== $actor) {
 164 |                 abort(403);
 165 |             }
 166 |             if ($fingerprint !== null && ! hash_equals($this->closingFingerprint($shiftId), $fingerprint)) {
 167 |                 $this->invalid('Rekap sudah berubah sejak popup dibuka; tutup ulang untuk melihat angka terbaru.');
 168 |             }
```

Isi fingerprint penutupan — `app/Services/V2/Operations.php:207-222`

```text
 207 |     public function closingFingerprint(int $shiftId): string
 208 |     {
 209 |         $shift = DB::table('v2_shifts')->where('id', $shiftId)->first(['status', 'opening_cash']);
 210 |         $movements = DB::table('v2_shift_movements')->where('shift_id', $shiftId)->orderBy('id')->get(['id', 'direction', 'kind', 'amount']);
 211 |         $sales = DB::table('v2_sales')->where('shift_id', $shiftId)->orderBy('id')->get(['id', 'payment_method', 'total']);
 212 |         $pending = DB::table('v2_payments')->where('shift_id', $shiftId)->where('method', 'cash')->where('status', 'proposed')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
 213 | 
 214 |         return hash('sha256', json_encode([
 215 |             'shift' => $shiftId,
 216 |             'status' => $shift->status ?? 'missing',
 217 |             'opening' => $shift ? (string) Money::value((string) $shift->opening_cash, 2) : '0.00',
 218 |             'movements' => $movements->map(fn ($m) => ['id' => (int) $m->id, 'direction' => $m->direction, 'kind' => $m->kind, 'amount' => (string) Money::value((string) $m->amount, 2)])->all(),
 219 |             'sales' => $sales->map(fn ($s) => ['id' => (int) $s->id, 'method' => $s->payment_method, 'total' => (string) Money::value((string) $s->total, 2)])->all(),
 220 |             'pending' => $pending,
 221 |         ], JSON_UNESCAPED_SLASHES));
 222 |     }
```

### KS15 — Tutup kas harian: cocok menjadi closed, selisih menjadi review

Server menjumlah v2_shift_movements pada shift: expected=opening_cash+in−out; difference=counted−expected. Nol -> closed, selain nol -> review. Simpan expected_cash, counted_cash, difference, status, closed_by, closed_at, hapus open_user_id dan store_open_key; hasil JSON mengembalikan expected/counted/difference/status. Tidak ada jurnal selisih atau pemindahan dana otomatis ke bank (setor ke bank dicatat terpisah lewat Pindah Dana JL9).

Hitung expected dan status hasil — `app/Services/V2/Operations.php:170-184`

```text
 170 |             $in = Money::value('0');
 171 |             $out = Money::value('0');
 172 |             foreach (DB::table('v2_shift_movements')->where('shift_id', $shiftId)->get(['direction', 'amount']) as $movement) {
 173 |                 if ($movement->direction === 'in') {
 174 |                     $in = $in->plus((string) $movement->amount);
 175 |                 } else {
 176 |                     $out = $out->plus((string) $movement->amount);
 177 |                 }
 178 |             }
 179 | 
 180 |             $expected = Money::value((string) $shift->opening_cash, 2)
 181 |                 ->plus((string) $in)->minus((string) $out)->toScale(2, RoundingMode::HalfUp);
 182 |             $difference = $count->minus($expected)->toScale(2, RoundingMode::HalfUp);
 183 |             $status = $difference->isZero() ? 'closed' : 'review';
 184 | 
```

Simpan penutupan, closed_by dan kembalikan hasil — `app/Services/V2/Operations.php:185-202`

```text
 185 |             DB::table('v2_shifts')->where('id', $shiftId)->update([
 186 |                 'expected_cash' => $this->str($expected),
 187 |                 'counted_cash' => $this->str($count),
 188 |                 'difference' => $this->str($difference),
 189 |                 'status' => $status,
 190 |                 'open_user_id' => null,
 191 |                 'store_open_key' => null,
 192 |                 'closed_by' => $actor,
 193 |                 'closed_at' => now(),
 194 |                 'updated_at' => now(),
 195 |             ]);
 196 | 
 197 |             return [
 198 |                 'expected' => $this->str($expected),
 199 |                 'counted' => $this->str($count),
 200 |                 'difference' => $this->str($difference),
 201 |                 'status' => $status,
 202 |             ];
```

Carry hanya dari kas harian closed dengan uang hitung — `app/Services/V2/Operations.php:86-100`

```text
  86 |                 if ($carryFrom !== null) {
  87 |                     $prev = DB::table('v2_shifts')->where('id', $carryFrom)->lockForUpdate()->first();
  88 |                     if (! $prev || $prev->status !== 'closed') {
  89 |                         $this->invalid('Shift asal harus sudah ditutup.');
  90 |                     }
  91 |                     if (! $prev->is_shared && (int) $prev->user_id !== $actor) {
  92 |                         abort(403);
  93 |                     }
  94 |                     if ($prev->counted_cash === null) {
  95 |                         $this->invalid('Shift asal belum memiliki uang hitung.');
  96 |                     }
  97 |                     $counted = Money::value((string) $prev->counted_cash, 2);
  98 |                     if ($counted->isNegative()) {
  99 |                         $this->invalid('Uang hitung shift asal negatif.');
 100 |                     }
```

### KS16 — Resolusi selisih kas harian oleh admin dan carry ke hari berikutnya

Review -> closed dengan reviewed_by, reviewed_at dan review_note; expected/count/difference historis tidak diganti dan tidak ada jurnal koreksi otomatis. Pemanggilan ulang closed oleh admin dan alasan identik no-op; status lain/closed dengan alasan lain ditolak. Setelah closed, admin dapat memakai counted_cash sebagai sumber carry/both pada pembukaan kas harian tanggal berikutnya (KS02) dengan penjaga sekali pakai.

Admin, alasan dan replay resolusi — `app/Services/V2/Operations.php:224-243`

```text
 224 |     public function resolveShift(int $actor, int $shiftId, string $reason): void
 225 |     {
 226 |         $this->requireRole($actor, ['admin']);
 227 |         $note = trim($reason);
 228 |         $length = mb_strlen($note);
 229 |         if ($length < 5 || $length > 1000) {
 230 |             $this->invalid('Alasan resolusi 5 sampai 1000 karakter.');
 231 |         }
 232 | 
 233 |         DB::transaction(function () use ($actor, $shiftId, $note) {
 234 |             $shift = DB::table('v2_shifts')->where('id', $shiftId)->lockForUpdate()->first();
 235 |             if (! $shift) {
 236 |                 $this->invalid('Shift tidak ditemukan.');
 237 |             }
 238 |             if ($shift->status !== 'review') {
 239 |                 if ($shift->status === 'closed' && (int) $shift->reviewed_by === $actor && $shift->review_note === $note) {
 240 |                     return;
 241 |                 }
 242 |                 $this->invalid('Hanya shift berstatus review yang dapat diresolusi.');
 243 |             }
```

Hanya status dan audit review diperbarui — `app/Services/V2/Operations.php:245-252`

```text
 245 |             DB::table('v2_shifts')->where('id', $shiftId)->update([
 246 |                 'status' => 'closed',
 247 |                 'reviewed_by' => $actor,
 248 |                 'reviewed_at' => now(),
 249 |                 'review_note' => $note,
 250 |                 'updated_at' => now(),
 251 |             ]);
 252 |         });
```

UI pemeriksaan selisih dan endpoint resolve — `resources/views/v2/sprint2.blade.php:58-75`

```text
  58 | <section class="card" aria-labelledby="review-heading">
  59 | <h2 id="review-heading">Pemeriksaan selisih kas harian</h2>
  60 | <p class="note">Mencakup semua tanggal. Mencatat pemeriksaan mengurangi daftar belum diselesaikan, tetapi tidak menghapus riwayat selisih atau membuat jurnal penyesuaian kas.</p>
  61 | <div data-live-list>
  62 | @if (count($reviewShifts) === 0)
  63 |     <p class="note">Tidak ada selisih kas harian yang perlu diperiksa.</p>
  64 | @else
  65 |     @foreach ($reviewShifts as $shift)
  66 |     <form class="v2-json-form" action="{{ route('v2.shifts.resolve', $shift->id) }}">
  67 |         <p>Kas harian {{ substr((string) $shift->opened_at, 0, 10) }}: fisik {{ $shift->counted_cash }}; selisih {{ $shift->difference }}. Angka historis tidak diubah.</p>
  68 |         <div class="field">
  69 |             <label for="review-{{ $shift->id }}">Catatan pemeriksaan</label>
  70 |             <input id="review-{{ $shift->id }}" name="reason" required minlength="5" maxlength="1000">
  71 |         </div>
  72 |         <button type="submit">Catat pemeriksaan admin</button>
  73 |         <p data-error class="err" tabindex="-1" hidden></p>
  74 |     </form>
  75 |     @endforeach
```

## 04-pengiriman-perjalanan — Pengiriman, perjalanan, dan pertanggungjawaban uang muka

### PG1 — Tetapkan kendaraan dan sopir pengiriman

Prepared yang belum ditugaskan mendapat snapshot driver_id, driver_name dan vehicle_id. Penugasan lama harus cocok. Kendaraan wajib active; sopir diperiksa melalui MasterRelations::driver dan pegawai internal aktif. Nama historis tanpa driver_id ditolak untuk rekonsiliasi manual, tidak ditebak.

Penolakan nama historis dan ketidakcocokan ID — `app/Services/V2/TransportAssignment.php:24-31`

```text
  24 |     public function assertMatches(object $delivery, int $driverId, int $vehicleId): void
  25 |     {
  26 |         if ($delivery->driver_id === null && $delivery->driver_name !== null) {
  27 |             $this->invalid('Pengiriman ini hanya punya nama sopir historis tanpa master sopir. Rekonsiliasi manual diperlukan; identitas tidak ditebak dari nama.');
  28 |         }
  29 |         if ((int) $delivery->driver_id !== $driverId || (int) $delivery->vehicle_id !== $vehicleId) {
  30 |             $this->invalid('Sopir dan kendaraan harus sesuai penugasan pengiriman.');
  31 |         }
```

Master aktif dan snapshot penugasan — `app/Services/V2/TransportAssignment.php:47-59`

```text
  47 |         $driver = $this->relations->driver($driverId);
  48 |         $vehicle = DB::table('vehicles')->where('id', $vehicleId)->lockForUpdate()->first();
  49 |         if ($vehicle === null || $vehicle->status !== 'active') {
  50 |             $this->invalid('Kendaraan tidak ditemukan atau tidak aktif.');
  51 |         }
  52 | 
  53 |         if ($unassigned) {
  54 |             DB::table('v2_deliveries')->where('id', $delivery->id)->update([
  55 |                 'driver_id' => $driverId,
  56 |                 'driver_name' => $driver->name,
  57 |                 'vehicle_id' => $vehicleId,
  58 |                 'updated_at' => now(),
  59 |             ]);
```

### PG2 — Kirim pengiriman prepared menjadi sent

Penugasan dicek/dicatat; status sent dan dispatched_by/at tersimpan. Kirim ulang status sent dengan identitas sama tidak berefek; pengiriman received ditolak. Tidak mengurangi stok lagi dan tidak membuat jurnal.

Replay, final diterima, dan transisi sent — `app/Services/V2/DeliveryTrips.php:47-66`

```text
  47 |             $delivery = $this->assignment->delivery($deliveryId);
  48 |             $this->access($actor, $delivery);
  49 | 
  50 |             if ($delivery->status === 'sent') {
  51 |                 $this->assignment->assertMatches($delivery, $driverId, $vehicleId);
  52 | 
  53 |                 return; // kirim ulang identik: tanpa efek samping
  54 |             }
  55 |             if ($delivery->status === 'received') {
  56 |                 $this->invalid('Pengiriman yang sudah diterima tidak dapat dikirim ulang.');
  57 |             }
  58 | 
  59 |             $this->assignment->resolve($driverId, $vehicleId, $delivery);
  60 | 
  61 |             DB::table('v2_deliveries')->where('id', $deliveryId)->update([
  62 |                 'status' => 'sent',
  63 |                 'dispatched_by' => $actor,
  64 |                 'dispatched_at' => now(),
  65 |                 'updated_at' => now(),
  66 |             ]);
```

Guard akses nyata admin atau pemilik sale — `app/Services/V2/DeliveryTrips.php:388-401`

```text
 388 |     private function access(int $actor, object $delivery): void
 389 |     {
 390 |         $role = DB::table('v2_user_roles')->where('user_id', $actor)->value('role');
 391 |         if ($role === 'admin') {
 392 |             return;
 393 |         }
 394 |         if ($role === 'cashier') {
 395 |             $owner = DB::table('v2_sales')->where('id', $delivery->sale_id)->value('user_id');
 396 |             if ((int) $owner === $actor) {
 397 |                 return;
 398 |             }
 399 |         }
 400 |         abort(403);
 401 |     }
```

### PG3 — Konfirmasi barang kiriman diterima

Status received dan received_by/at tersimpan. Terima ulang received tidak berefek; prepared ditolak karena belum dikirim. Ini penerimaan pelanggan, BUKAN penerimaan stok supplier; tidak menulis stok/jurnal/pembayaran.

Sent wajib sebelum received; audit penerimaan — `app/Services/V2/DeliveryTrips.php:80-96`

```text
  80 |             $delivery = $this->assignment->delivery($deliveryId);
  81 |             $this->access($actor, $delivery);
  82 | 
  83 |             if ($delivery->status === 'received') {
  84 |                 return; // terima ulang identik: tanpa efek samping
  85 |             }
  86 |             if ($delivery->status !== 'sent') {
  87 |                 $this->invalid('Pengiriman harus dikirim terlebih dahulu sebelum diterima.');
  88 |             }
  89 | 
  90 |             DB::table('v2_deliveries')->where('id', $deliveryId)->update([
  91 |                 'status' => 'received',
  92 |                 'received_by' => $actor,
  93 |                 'received_at' => now(),
  94 |                 'updated_at' => now(),
  95 |             ]);
  96 |         });
```

### PG4 — Catat biaya perjalanan langsung (tunai laci atau rekening bank)

v2_trips posted; jurnal DR 6101 beban perjalanan, CR 1101 tunai atau rekening bank aktif terpilih. Cash memerlukan kas harian bersama terbuka, saldo laci cukup, mutasi out adjustment; transfer tanpa mutasi laci. Keduanya memeriksa saldo akun. Replay muatan identik mengembalikan hasil tersimpan; tidak mengubah stok dan bukan pengurang pendapatan ongkir 4102.

Cash memakai kas harian bersama dan saldo laci/akun cukup — `app/Services/V2/SupplyTrips.php:361-381`

```text
 361 |                 $account = $method === 'cash' ? self::CASH : ($bankAccountCode ?? self::BANK);
 362 |                 $shift = null;
 363 | 
 364 |                 if ($method === 'cash') {
 365 |                     $shift = app(Operations::class)->activeStoreShift(true);
 366 |                     if (! $shift) {
 367 |                         $this->invalid('Biaya tunai wajib memakai shift toko yang dibuka admin.');
 368 |                     }
 369 | 
 370 |                     $drawer = Money::value((string) $shift->opening_cash);
 371 |                     foreach (DB::table('v2_shift_movements')->where('shift_id', $shift->id)->get(['direction', 'amount']) as $movement) {
 372 |                         $drawer = $movement->direction === 'in'
 373 |                             ? $drawer->plus((string) $movement->amount)
 374 |                             : $drawer->minus((string) $movement->amount);
 375 |                     }
 376 |                     if ($drawer->isLessThan($total)) {
 377 |                         $this->invalid('Saldo laci kas tidak cukup untuk biaya perjalanan.');
 378 |                     }
 379 |                 }
 380 | 
 381 |                 $this->guardAccount($account, $total);
```

Jurnal biaya (ke 1101 atau rekening bank) dan mutasi laci bersyarat — `app/Services/V2/SupplyTrips.php:408-430`

```text
 408 |                 $journalId = Ledger::post('v2_trip', $tripId, 'Biaya perjalanan '.$destination, [
 409 |                     ['account_code' => self::TRIP_EXPENSE, 'debit' => $this->str($total), 'credit' => '0.00'],
 410 |                     ['account_code' => $account, 'debit' => '0.00', 'credit' => $this->str($total)],
 411 |                 ], $date);
 412 | 
 413 |                 DB::table('v2_trips')->where('id', $tripId)->update([
 414 |                     'journal_entry_id' => $journalId,
 415 |                     'updated_at' => now(),
 416 |                 ]);
 417 | 
 418 |                 if ($shift !== null) {
 419 |                     DB::table('v2_shift_movements')->insert([
 420 |                         'shift_id' => (int) $shift->id,
 421 |                         'direction' => 'out',
 422 |                         'kind' => 'adjustment',
 423 |                         'amount' => $this->str($total),
 424 |                         'reference_type' => 'v2_trip',
 425 |                         'reference_id' => $tripId,
 426 |                         'note' => 'Biaya perjalanan '.$destination.' (pengeluaran trip, bukan penyesuaian umum)',
 427 |                         'created_at' => now(),
 428 |                         'updated_at' => now(),
 429 |                     ]);
 430 |                 }
```

### PG5 — Keluarkan uang muka sopir (tunai laci atau rekening bank)

Uang muka status open dan referensi jurnal tersimpan; DR 1191 aset uang muka, CR 1101 atau rekening bank aktif terpilih. Cash menulis mutasi out pada kas harian bersama; transfer tidak. Belum membebankan 6101 saat uang muka keluar. Replay sama tidak mengeluarkan uang dua kali.

Sumber uang muka (laci atau rekening bank) dan penjaga saldo — `app/Services/V2/DeliveryTrips.php:159-170`

```text
 159 |                 $account = $method === 'cash' ? self::CASH : ($bankAccountCode ?? self::BANK);
 160 |                 $shift = null;
 161 |                 if ($method === 'cash') {
 162 |                     $shift = $this->openShift($actor);
 163 |                     if ($this->drawerBalance($shift)->isLessThan($amount)) {
 164 |                         $this->invalid('Saldo laci kas tidak cukup untuk uang muka ini.');
 165 |                     }
 166 |                 }
 167 | 
 168 |                 $this->requireAccount(self::ADVANCE_ASSET);
 169 |                 $this->guardAccount($account, $amount);
 170 | 
```

Posting aset uang muka dan laci — `app/Services/V2/DeliveryTrips.php:194-216`

```text
 194 |                 $journalId = Ledger::post('v2_driver_advance', $advanceId, 'Uang muka sopir '.$driver->name.' '.$date, [
 195 |                     ['account_code' => self::ADVANCE_ASSET, 'debit' => $this->str($amount), 'credit' => '0.00'],
 196 |                     ['account_code' => $account, 'debit' => '0.00', 'credit' => $this->str($amount)],
 197 |                 ], $date);
 198 | 
 199 |                 DB::table('v2_driver_advances')->where('id', $advanceId)->update([
 200 |                     'journal_entry_id' => $journalId,
 201 |                     'updated_at' => $now,
 202 |                 ]);
 203 | 
 204 |                 if ($shift !== null) {
 205 |                     DB::table('v2_shift_movements')->insert([
 206 |                         'shift_id' => (int) $shift->id,
 207 |                         'direction' => 'out',
 208 |                         'kind' => 'adjustment',
 209 |                         'amount' => $this->str($amount),
 210 |                         'reference_type' => 'v2_driver_advance',
 211 |                         'reference_id' => $advanceId,
 212 |                         'note' => 'Uang muka sopir '.$driver->name,
 213 |                         'created_at' => $now,
 214 |                         'updated_at' => $now,
 215 |                     ]);
 216 |                 }
```

### PG6 — Periksa biaya aktual dan sisa uang muka

Refund dihitung amount - actual. Sisa positif wajib refund_received=true dan refund_method cash/transfer (dengan refund_bank_account_code opsional bila transfer); tunai kembali memerlukan kas harian bersama terbuka. Biaya aktual lebih besar ditolak, bukan otomatis menambah pengeluaran.

Advance terkunci, batas aktual, dan bukti sisa diterima — `app/Services/V2/DeliveryTrips.php:278-300`

```text
 278 |                 $advance = DB::table('v2_driver_advances')->where('id', $advanceId)->lockForUpdate()->first();
 279 |                 if (! $advance) {
 280 |                     $this->invalid('Uang muka tidak ditemukan.');
 281 |                 }
 282 |                 if ($advance->status === 'settled') {
 283 |                     $this->invalid('Uang muka ini sudah diselesaikan.');
 284 |                 }
 285 | 
 286 |                 $this->requireAccount(self::ADVANCE_ASSET);
 287 |                 $advanceAmount = Money::value((string) $advance->amount);
 288 |                 if ($actual->isGreaterThan($advanceAmount)) {
 289 |                     $this->invalid('Biaya aktual melebihi uang muka; selisih lebih harus dicatat sebagai pengeluaran terpisah (belum didukung).');
 290 |                 }
 291 | 
 292 |                 $refund = $advanceAmount->minus($actual);
 293 |                 if ($refund->isPositive()) {
 294 |                     if (! $refundReceived) {
 295 |                         $this->invalid('Sisa uang muka '.$this->str($refund).' harus dikonfirmasi dengan refund_received=true.');
 296 |                     }
 297 |                     if ($refundMethod === null) {
 298 |                         $this->invalid('Metode pengembalian sisa wajib diisi (cash atau transfer).');
 299 |                     }
 300 |                 }
```

### PG7 — Posting pertanggungjawaban dan kunci settled

Buat v2_driver_advance_settlements settled. DR 6101 sebesar aktual positif + DR 1101 atau rekening bank pengembalian sebesar refund positif; CR 1191 sebesar seluruh uang muka. Kaitkan jurnal lalu advance.status=settled. Refund cash menulis mutasi in pada kas harian bersama. Tidak membuat v2_trips kedua.

Komposisi jurnal aktual dan pengembalian ke kas/rekening bank — `app/Services/V2/DeliveryTrips.php:331-345`

```text
 331 |                 $lines = [];
 332 |                 if ($actual->isPositive()) {
 333 |                     $lines[] = ['account_code' => self::TRIP_EXPENSE, 'debit' => $this->str($actual), 'credit' => '0.00'];
 334 |                 }
 335 |                 if ($refund->isPositive()) {
 336 |                     $lines[] = ['account_code' => $account, 'debit' => $this->str($refund), 'credit' => '0.00'];
 337 |                 }
 338 |                 $lines[] = ['account_code' => self::ADVANCE_ASSET, 'debit' => '0.00', 'credit' => $this->str($advanceAmount)];
 339 | 
 340 |                 $journalId = Ledger::post(
 341 |                     'v2_driver_advance_settlement',
 342 |                     $settlementId,
 343 |                     'Penyelesaian uang muka sopir '.$advance->driver_name,
 344 |                     $lines
 345 |                 );
```

Settled dan refund ke kas harian bersama — `app/Services/V2/DeliveryTrips.php:347-368`

```text
 347 |                 DB::table('v2_driver_advance_settlements')->where('id', $settlementId)->update([
 348 |                     'journal_entry_id' => $journalId,
 349 |                     'updated_at' => now(),
 350 |                 ]);
 351 |                 DB::table('v2_driver_advances')->where('id', $advanceId)->update([
 352 |                     'status' => 'settled',
 353 |                     'updated_at' => now(),
 354 |                 ]);
 355 | 
 356 |                 if ($shift !== null) {
 357 |                     DB::table('v2_shift_movements')->insert([
 358 |                         'shift_id' => (int) $shift->id,
 359 |                         'direction' => 'in',
 360 |                         'kind' => 'adjustment',
 361 |                         'amount' => $this->str($refund),
 362 |                         'reference_type' => 'v2_driver_advance_settlement',
 363 |                         'reference_id' => $settlementId,
 364 |                         'note' => 'Sisa uang muka sopir '.$advance->driver_name,
 365 |                         'created_at' => now(),
 366 |                         'updated_at' => now(),
 367 |                     ]);
 368 |                 }
```

## 05-jurnal-laporan — Jurnal, pengeluaran, kelola kas/bank, laporan harian, dan laporan keuangan

### JL1 — Validasi dan posting jurnal dari transaksi

Tolak referensi/tanggal/akun/nilai tidak sah, baris dua sisi sekaligus, nol, total nol atau debit tidak sama kredit. Dalam transaksi kunci akun aktif urut kode; akun hilang menggagalkan posting, tidak dibuat otomatis. Simpan journal_entries dan journal_entry_lines dengan nama akun dari COA. Rollback mengikuti transaksi pemanggil bila gagal.

Total jurnal seimbang dan akun aktif harus tersedia — `app/Services/V2/Ledger.php:79-105`

```text
  79 |         if ($debitTotal->isZero()) {
  80 |             throw new InvalidArgumentException('Total jurnal tidak boleh nol.');
  81 |         }
  82 | 
  83 |         if (! $debitTotal->isEqualTo($creditTotal)) {
  84 |             throw new InvalidArgumentException(
  85 |                 'Jurnal tidak seimbang: debit '.$debitTotal.' vs kredit '.$creditTotal.'.'
  86 |             );
  87 |         }
  88 | 
  89 |         return DB::transaction(function () use ($referenceType, $referenceId, $description, $rows, $date) {
  90 |             $codes = array_values(array_unique(array_column($rows, 'account_code')));
  91 |             sort($codes); // urutan kunci konsisten -> mengurangi deadlock
  92 | 
  93 |             $accounts = DB::table('chart_of_accounts')
  94 |                 ->whereIn('account_code', $codes)
  95 |                 ->where('is_active', true)
  96 |                 ->orderBy('account_code')
  97 |                 ->lockForUpdate()
  98 |                 ->pluck('account_name', 'account_code');
  99 | 
 100 |             foreach ($codes as $code) {
 101 |                 if (! isset($accounts[$code])) {
 102 |                     throw new InvalidArgumentException(
 103 |                         'Akun '.$code.' belum ada di bagan akun. Tambahkan akun tersebut lebih dahulu; jurnal dibatalkan.'
 104 |                     );
 105 |                 }
```

Header jurnal dan baris akun dari COA — `app/Services/V2/Ledger.php:110-127`

```text
 110 |             $journalId = DB::table('journal_entries')->insertGetId([
 111 |                 'reference_type' => $referenceType,
 112 |                 'reference_id' => $referenceId,
 113 |                 'description' => $description,
 114 |                 'transaction_date' => $date ?? $now->toDateString(),
 115 |                 'created_at' => $now,
 116 |                 'updated_at' => $now,
 117 |             ]);
 118 | 
 119 |             DB::table('journal_entry_lines')->insert(array_map(
 120 |                 fn (array $row) => $row + [
 121 |                     'journal_entry_id' => $journalId,
 122 |                     'account_name' => $accounts[$row['account_code']],
 123 |                     'created_at' => $now,
 124 |                     'updated_at' => $now,
 125 |                 ],
 126 |                 $rows
 127 |             ));
```

### JL2 — Baca laporan operasional harian (termasuk rincian per rekening bank)

Baca seluruh penjualan dalam kas harian terpilih, pisahkan pendapatan barang/diskon/ongkir; expected=opening+seluruh in-seluruh out. Collections hanya in jenis sale/collection. Counted/difference null bila belum dihitung. Bank posted memakai tanggal jurnal; pending/proposed memakai tanggal pengajuan, dan dirinci per rekening bank aktif/historis pada bank.by_account. Laporan read-only, tidak menutup periode/jurnal.

Ruang lingkup admin/kasir dan hari bisnis kas harian — `app/Services/V2/ReportsDocuments.php:43-58`

```text
  43 |         $this->assertDate($date);
  44 |         $role = $this->operations->role($actor);
  45 |         $own = $role !== 'admin';
  46 | 
  47 |         $shiftQuery = DB::table('v2_shifts')
  48 |             ->whereDate('opened_at', $date)
  49 |             ->when($own, fn ($q) => $q->where(fn ($visible) => $visible->where('is_shared', true)->orWhere('user_id', $actor)));
  50 |         $shifts = $shiftQuery->get(['id', 'opening_cash', 'expected_cash', 'counted_cash', 'difference', 'status', 'is_shared']);
  51 |         $shiftIds = $shifts->pluck('id')->map(fn ($id) => (int) $id)->all();
  52 | 
  53 |         $sales = $shiftIds === []
  54 |             ? collect()
  55 |             : DB::table('v2_sales')
  56 |                 ->whereIn('shift_id', $shiftIds)
  57 |                 ->when($own, fn ($q) => $q->where('user_id', $actor))
  58 |                 ->get(['subtotal', 'line_discount', 'invoice_discount', 'delivery_fee', 'total']);
```

Rincian penerimaan bank per rekening (bank.by_account) — `app/Services/V2/ReportsDocuments.php:145-172`

```text
 145 |         $bankNames = app(BankAccounts::class)->nameMap(false);
 146 |         $byBank = [];
 147 |         foreach ($payments as $payment) {
 148 |             $bankCode = trim((string) ($payment->bank_account_code ?? '')) ?: BankAccounts::DEFAULT_BANK;
 149 |             if (! isset($byBank[$bankCode])) {
 150 |                 $byBank[$bankCode] = [
 151 |                     'code' => $bankCode,
 152 |                     'name' => $bankNames[$bankCode] ?? ($bankCode === BankAccounts::DEFAULT_BANK ? 'Bank Utama' : $bankCode),
 153 |                     'posted' => $this->zero(),
 154 |                     'pending' => $this->zero(),
 155 |                 ];
 156 |             }
 157 |             $amount = $this->money($payment->amount);
 158 |             if ($payment->status === 'posted') {
 159 |                 $posted = $posted->plus($amount);
 160 |                 $byBank[$bankCode]['posted'] = $byBank[$bankCode]['posted']->plus($amount);
 161 |             } elseif (in_array($payment->status, ['pending', 'proposed'], true)) {
 162 |                 $pending = $pending->plus($amount);
 163 |                 $byBank[$bankCode]['pending'] = $byBank[$bankCode]['pending']->plus($amount);
 164 |             }
 165 |         }
 166 |         ksort($byBank);
 167 |         $bankBreakdown = array_values(array_map(fn (array $row): array => [
 168 |             'code' => $row['code'],
 169 |             'name' => $row['name'],
 170 |             'posted' => $this->str($row['posted']),
 171 |             'pending' => $this->str($row['pending']),
 172 |         ], $byBank));
```

### JL3 — Buka nota dan surat jalan penjualan

Baca snapshot sale/items beserta pelanggan dan pengiriman untuk cetak; hitung goods_net=total-delivery_fee dari snapshot. Tidak mengubah stok, status pengiriman, piutang atau jurnal; membuka surat jalan bukan otomatis dispatch/receive.

Akses cetak nota/surat jalan dan snapshot penjualan — `app/Services/V2/ReportsDocuments.php:215-225`

```text
 215 |         $this->operations->requireRole($actor, ['admin', 'cashier']);
 216 | 
 217 |         $sale = DB::table('v2_sales')->where('id', $saleId)->first();
 218 |         if (! $sale) {
 219 |             $this->fail('Penjualan tidak ditemukan.');
 220 |         }
 221 | 
 222 |         $items = DB::table('v2_sale_items')
 223 |             ->where('sale_id', $saleId)
 224 |             ->orderBy('id')
 225 |             ->get(['product_id', 'name', 'quantity', 'unit_price', 'cost_price', 'discount', 'subtotal'])
```

Surat jalan harus punya pengiriman sale yang sama — `app/Services/V2/ReportsDocuments.php:255-261`

```text
 255 |         if ($kind === 'delivery' && $delivery === null) {
 256 |             $this->fail('Penjualan ini tidak memiliki data pengiriman.');
 257 |         }
 258 | 
 259 |         $total = $this->money($sale->total);
 260 |         $fee = $this->money($sale->delivery_fee);
 261 | 
```

### JL4 — Baca buku jurnal, rincian saldo kas/rekening bank, dan feed pembaruan

Baca header dan baris jurnal tersimpan; saldo Kas 1101 dan saldo per Rekening Bank aktif dihitung melalui AccountBalanceService dan BankAccounts::balancesByAccount; feed after_id membaca jurnal lebih baru tanpa menulis jurnal. Penyesuaian saldo dan pindah dana di V2 dijalankan lewat endpoint V2 khusus (JL9).

Arsip legacy read-only admin saat cutover — `app/Http/Middleware/V2Cutover.php:14-28`

```text
  14 |         if (config('v2.enabled') && $request->is('demo-setup', 'demo-setup/*')) {
  15 |             abort(404);
  16 |         }
  17 |         if (! config('v2.enabled') || $request->is('v2', 'v2/*', 'login', 'logout', 'up')) {
  18 |             return $next($request);
  19 |         }
  20 |         if (! Auth::check()) {
  21 |             return $next($request);
  22 |         }
  23 |         $role = app(Operations::class)->role(Auth::id());
  24 |         if ($request->path() === '/') {
  25 |             return $next($request);
  26 |         }
  27 |         // Legacy remains an admin read-only archive after cutover.
  28 |         abort_unless($role === 'admin' && $request->isMethod('GET'), 403, 'Gunakan alur V2; arsip lama hanya dapat dibaca admin.');
```

Saldo Kas 1101, rincian per Rekening Bank, dan daftar akun — `app/Http/Controllers/JournalController.php:37-54`

```text
  37 |         $cashOpening = (string) ($opening->cash_amount ?? '0.00');
  38 |         $bankOpening = (string) ($opening->bank_amount ?? '0.00');
  39 |         $cashBalance = (string) $balances->balance('1101');
  40 |         $bankAccounts = $bankService->balancesByAccount($balances, true);
  41 |         $bankBalance = (string) $bankService->totalBalance($balances, false);
  42 |         $sources = DB::table('journal_entries')->distinct()->orderBy('reference_type')->pluck('reference_type');
  43 |         $hasJournals = DB::table('journal_entries')->exists();
  44 |         $adjustableCodes = $bankService->cashAndBankCodes(true);
  45 |         $adjustmentAccounts = DB::table('chart_of_accounts')->where('is_active', true)
  46 |             ->when(config('v2.enabled'), fn ($query) => $query->whereIn('account_code', $adjustableCodes))
  47 |             ->orderBy('account_code')->get();
  48 |         $counterAccounts = config('v2.enabled')
  49 |             ? DB::table('chart_of_accounts')->where('is_active', true)->orderBy('account_code')->get()
  50 |             : $adjustmentAccounts;
  51 | 
  52 |         return view('journals.index', compact(
  53 |             'entries', 'opening', 'cashOpening', 'bankOpening', 'cashBalance', 'bankBalance', 'bankAccounts', 'sources', 'filters', 'adjustmentAccounts', 'counterAccounts', 'hasJournals'
  54 |         ));
```

### JL5 — Hitung laba rugi dari baris jurnal

Kelompokkan 4xxx pendapatan, 5xxx HPP, 6xxx beban, 7xxx penghasilan lain, 8xxx beban lain. Pendapatan=kredit-debit; beban=debit-kredit. Laba bersih=pendapatan-HPP-beban+penghasilan lain-beban lain. Semua perhitungan BigDecimal; keluaran HTML/JSON atau CSV terpisah, tanpa menulis saldo atau menutup buku.

Sumber baris jurnal dan filter tanggal laba rugi — `app/Services/AccountingReportService.php:38-56`

```text
  38 |     protected function fetchProfitAndLossLines(?string $startDate = null, ?string $endDate = null): Collection
  39 |     {
  40 |         $query = DB::table('journal_entry_lines')
  41 |             ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
  42 |             ->leftJoin('chart_of_accounts', 'journal_entry_lines.account_code', '=', 'chart_of_accounts.account_code');
  43 | 
  44 |         if ($startDate) {
  45 |             $query->where('journal_entries.transaction_date', '>=', $startDate);
  46 |         }
  47 |         if ($endDate) {
  48 |             $query->where('journal_entries.transaction_date', '<=', $endDate);
  49 |         }
  50 | 
  51 |         return $query->select(
  52 |             'journal_entry_lines.account_code',
  53 |             DB::raw('COALESCE(MAX(chart_of_accounts.account_name), MAX(journal_entry_lines.account_name), journal_entry_lines.account_code) as account_name'),
  54 |             DB::raw('MAX(chart_of_accounts.account_type) as coa_type'),
  55 |             DB::raw('SUM(journal_entry_lines.debit) as total_debit'),
  56 |             DB::raw('SUM(journal_entry_lines.credit) as total_credit')
```

Rumus laba dan hasil terformat — `app/Services/AccountingReportService.php:170-187`

```text
 170 |         $grossProfit = $totalRevenue->minus($totalCogs);
 171 |         $operatingProfit = $grossProfit->minus($totalExpense);
 172 |         $netProfit = $operatingProfit->plus($totalOtherIncome)->minus($totalOtherExpense);
 173 | 
 174 |         return [
 175 |             'revenues' => $revenues,
 176 |             'total_revenue' => (string) $totalRevenue->toScale(2),
 177 |             'cogs' => $cogs,
 178 |             'total_cogs' => (string) $totalCogs->toScale(2),
 179 |             'gross_profit' => (string) $grossProfit->toScale(2),
 180 |             'expenses' => $expenses,
 181 |             'total_expense' => (string) $totalExpense->toScale(2),
 182 |             'other_income' => $otherIncome,
 183 |             'total_other_income' => (string) $totalOtherIncome->toScale(2),
 184 |             'other_expenses' => $otherExpenses,
 185 |             'total_other_expense' => (string) $totalOtherExpense->toScale(2),
 186 |             'net_profit' => (string) $netProfit->toScale(2),
 187 |         ];
```

### JL6 — Hitung neraca per tanggal dan keseimbangan

Tambahkan laba sampai as_of_date sebagai laba ditahan pada sisi ekuitas. is_balanced mensyaratkan aset=kewajiban+ekuitas+laba DAN setiap jurnal tidak kosong serta debit=kredit (journalsAreBalanced). Hanya pembacaan, bukan membuat jurnal penutup atau approval bisnis.

Persamaan neraca plus pemeriksaan setiap jurnal — `app/Services/AccountingReportService.php:281-297`

```text
 281 |         $pl = $this->generateProfitAndLoss(null, $asOfDate);
 282 |         $netProfit = BigDecimal::of((string) ($pl['net_profit'] ?? '0'));
 283 | 
 284 |         $totalLiabilitiesAndEquity = $totalLiability->plus($totalEquity)->plus($netProfit);
 285 |         $totalEquityWithRetained = $totalEquity->plus($netProfit);
 286 | 
 287 |         return [
 288 |             'assets' => $assets,
 289 |             'total_assets' => (string) $totalAsset->toScale(2),
 290 |             'liabilities' => $liabilities,
 291 |             'total_liabilities' => (string) $totalLiability->toScale(2),
 292 |             'equity' => $equity,
 293 |             'retained_earnings' => (string) $netProfit->toScale(2),
 294 |             'total_equity' => (string) $totalEquityWithRetained->toScale(2),
 295 |             'total_liabilities_and_equity' => (string) $totalLiabilitiesAndEquity->toScale(2),
 296 |             'is_balanced' => $totalAsset->isEqualTo($totalLiabilitiesAndEquity) && $this->journalsAreBalanced($asOfDate),
 297 |         ];
```

Jurnal kosong/tidak seimbang menggagalkan status seimbang — `app/Services/AccountingReportService.php:207-223`

```text
 207 |         foreach ($rows as $row) {
 208 |             if ($entryId !== $row->entry_id) {
 209 |                 if ($entryId !== null && ! $debit->isEqualTo($credit)) {
 210 |                     return false;
 211 |                 }
 212 |                 $entryId = $row->entry_id;
 213 |                 $debit = BigDecimal::zero();
 214 |                 $credit = BigDecimal::zero();
 215 |             }
 216 |             if ($row->line_id === null) {
 217 |                 return false;
 218 |             }
 219 |             $debit = $debit->plus((string) $row->debit);
 220 |             $credit = $credit->plus((string) $row->credit);
 221 |         }
 222 | 
 223 |         return $debit->isEqualTo($credit);
```

### JL7 — Hitung arus kas dan rekonsiliasi kas serta semua rekening bank

Kelompok operasi/investasi/pendanaan menurut reference_type lalu akun lawan. Perpindahan internal kas↔bank maupun antar rekening bank dengan netCash nol otomatis dilewati dari arus bersih. Cek saldo awal+perubahan bersih=saldo akhir DAN jumlah rincian semua akun kas/bank=saldo akhir. is_reconciled bukan bukti uang fisik laci cocok.

Arus kas mengambil Kas 1101 dan seluruh Rekening Bank dari COA — `app/Services/AccountingReportService.php:304-316`

```text
 304 |     public function generateCashFlow(?string $startDate = null, ?string $endDate = null): array
 305 |     {
 306 |         $startDate = $this->sanitizeDate($startDate);
 307 |         $endDate = $this->sanitizeDate($endDate);
 308 |         $cashAccounts = app(BankAccounts::class)->cashAndBankCodes(false);
 309 | 
 310 |         // 1. Calculate Beginning Cash (Saldo Awal Kas & Bank sebelum startDate)
 311 |         $beginningCash = BigDecimal::zero();
 312 |         if ($startDate) {
 313 |             $beforeQuery = DB::table('journal_entry_lines')
 314 |                 ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
 315 |                 ->whereIn('journal_entry_lines.account_code', $cashAccounts)
 316 |                 ->where('journal_entries.transaction_date', '<', $startDate);
```

Arus kas neto dan pengecualian transfer internal kas/bank — `app/Services/AccountingReportService.php:424-437`

```text
 424 |                 foreach ($entryLines as $l) {
 425 |                     if (in_array($l->account_code, $cashAccounts, true)) {
 426 |                         $cashDebit = $cashDebit->plus((string) $l->debit);
 427 |                         $cashCredit = $cashCredit->plus((string) $l->credit);
 428 |                     } else {
 429 |                         $nonCashLines[] = $l;
 430 |                     }
 431 |                 }
 432 | 
 433 |                 $netCash = $cashDebit->minus($cashCredit);
 434 |                 if ($netCash->isZero()) {
 435 |                     // Internal transfer between cash and bank: net change is zero
 436 |                     continue;
 437 |                 }
```

Rekonsiliasi saldo awal, arus, saldo akhir, rincian kas-bank — `app/Services/AccountingReportService.php:671-687`

```text
 671 |         $netCashChange = $op['_net']->plus($inv['_net'])->plus($fin['_net']);
 672 |         $reconciledEnding = $beginningCash->plus($netCashChange);
 673 | 
 674 |         unset($op['_net'], $inv['_net'], $fin['_net']);
 675 | 
 676 |         return [
 677 |             'start_date' => $startDate,
 678 |             'end_date' => $endDate,
 679 |             'beginning_cash' => (string) $beginningCash->toScale(2),
 680 |             'operating_activities' => $op,
 681 |             'investing_activities' => $inv,
 682 |             'financing_activities' => $fin,
 683 |             'net_cash_change' => (string) $netCashChange->toScale(2),
 684 |             'ending_cash' => (string) $endingCash->toScale(2),
 685 |             'cash_breakdown' => $cashBreakdown,
 686 |             'is_reconciled' => $reconciledEnding->isEqualTo($endingCash) && $breakdownSum->isEqualTo($endingCash),
 687 |         ];
```

### JL8 — Catat pengeluaran operasional V2 (Kas Utama atau Rekening Bank)

Mengunci akun beban & sumber, memverifikasi saldo via AccountBalanceService::assertSufficient, menyimpan expenses (EXP-YYYYMMDD-######, bank_account_code bila bank) lalu memposting jurnal terkunci melalui Ledger::post (DR akun beban / CR 1101 atau rekening bank terpilih). Halaman GET /expenses mendukung filter sumber termasuk per rekening bank (bank:<kode>).

Validasi endpoint pengeluaran operasional V2 — `app/Http/Controllers/V2/OperatingExpenseController.php:14-38`

```text
  14 |     public function store(Request $request, OperatingExpenses $expenses): JsonResponse|RedirectResponse
  15 |     {
  16 |         $validated = $request->validate([
  17 |             'date' => 'nullable|date_format:Y-m-d',
  18 |             'account_code' => 'required|string|max:20',
  19 |             'source' => 'required|in:cash,bank',
  20 |             'bank_account_code' => 'nullable|string|max:20',
  21 |             'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
  22 |             'description' => 'nullable|string|max:255',
  23 |         ], [
  24 |             'date.date_format' => 'Tanggal pengeluaran tidak valid.',
  25 |             'account_code.required' => 'Pilih akun beban operasional.',
  26 |             'account_code.max' => 'Kode akun terlalu panjang.',
  27 |             'source.required' => 'Pilih sumber dana.',
  28 |             'source.in' => 'Sumber dana harus Kas Tunai atau Bank.',
  29 |             'bank_account_code.max' => 'Kode rekening bank terlalu panjang.',
  30 |             'amount.required' => 'Isi nominal pengeluaran.',
  31 |             'amount.numeric' => 'Nominal pengeluaran harus berupa angka.',
  32 |             'amount.gt' => 'Nominal pengeluaran harus lebih dari nol.',
  33 |             'amount.decimal' => 'Nominal pengeluaran maksimal 2 angka desimal.',
  34 |             'description.max' => 'Keterangan maksimal 255 karakter.',
  35 |         ]);
  36 | 
  37 |         try {
  38 |             $result = $expenses->post($request->user()->id, $validated);
```

Guard akun beban non-HPP dan saldo sumber cukup — `app/Services/V2/OperatingExpenses.php:73-90`

```text
  73 |             if (! $expense || ! $expense->is_active) {
  74 |                 throw ValidationException::withMessages(['account_code' => 'Akun beban tidak ditemukan atau tidak aktif.']);
  75 |             }
  76 |             if ($expense->account_type !== 'expense' || in_array($code, self::BLOCKED_ACCOUNTS, true)) {
  77 |                 throw ValidationException::withMessages(['account_code' => 'Pilih akun beban operasional; HPP/persediaan tidak boleh dipakai.']);
  78 |             }
  79 |             if (! $paidFrom || ! $paidFrom->is_active) {
  80 |                 throw ValidationException::withMessages(['source' => 'Akun Kas/Bank sumber dana tidak ditemukan atau tidak aktif.']);
  81 |             }
  82 |             if ($code === $sourceCode) {
  83 |                 throw ValidationException::withMessages(['account_code' => 'Akun beban tidak boleh sama dengan akun pembayaran.']);
  84 |             }
  85 | 
  86 |             try {
  87 |                 $this->balances->assertSufficient($sourceCode, (string) $amount);
  88 |             } catch (\InvalidArgumentException $exception) {
  89 |                 throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
  90 |             }
```

Simpan pengeluaran dan posting jurnal terkunci — `app/Services/V2/OperatingExpenses.php:106-123`

```text
 106 |             $expenseId = DB::table('expenses')->insertGetId($expenseRow);
 107 | 
 108 |             $number = sprintf('EXP-%s-%06d', str_replace('-', '', $date), $expenseId);
 109 |             DB::table('expenses')->where('id', $expenseId)->update(['expense_number' => $number, 'updated_at' => $now]);
 110 | 
 111 |             $journalId = Ledger::post(
 112 |                 'operating_expense',
 113 |                 $expenseId,
 114 |                 mb_substr('Pengeluaran '.$number.': '.$expense->account_name, 0, 255),
 115 |                 [
 116 |                     ['account_code' => $code, 'debit' => (string) $amount],
 117 |                     ['account_code' => $sourceCode, 'credit' => (string) $amount],
 118 |                 ],
 119 |                 $date
 120 |             );
 121 | 
 122 |             DB::table('expenses')->where('id', $expenseId)->update(['journal_entry_id' => $journalId, 'updated_at' => $now]);
 123 | 
```

### JL9 — Penyesuaian saldo dan pindah dana Kas/Bank V2 dengan konfirmasi password

Penyesuaian mengecek saldo kas/bank hasil tidak negatif dan membuat jurnal reference_type=adjustment seimbang tanpa mengubah saldo awal. Pindah dana mengecek saldo sumber cukup via assertSufficient dan membuat jurnal reference_type=cash_bank_transfer (DR tujuan / CR sumber); pada Laporan Arus Kas perpindahan internal ini menghasilkan arus bersih nol.

Penyesuaian saldo Kas/Bank tanpa saldo negatif — `app/Services/V2/CashBankAdjustments.php:78-97`

```text
  78 |             $counterCurrent = $this->balances->balance($counterCode);
  79 |             $counterAfter = $counterCurrent->minus($difference);
  80 |             $guardedAccounts = $this->bankAccounts->cashAndBankCodes(false);
  81 |             foreach ([[$code, $target], [$counterCode, $counterAfter]] as [$checkedCode, $result]) {
  82 |                 if (in_array($checkedCode, $guardedAccounts, true) && $result->isNegative()) {
  83 |                     throw ValidationException::withMessages([
  84 |                         'adjustment' => 'Penyesuaian tidak boleh membuat saldo Kas atau Bank negatif.',
  85 |                     ]);
  86 |                 }
  87 |             }
  88 | 
  89 |             $reason = trim((string) $data['description']);
  90 |             $journalId = DB::table('journal_entries')->insertGetId([
  91 |                 'reference_type' => 'adjustment',
  92 |                 'reference_id' => null,
  93 |                 'description' => mb_substr('Penyesuaian saldo '.$code.' oleh pengguna #'.$actor.': '.$reason, 0, 255),
  94 |                 'transaction_date' => $data['transaction_date'],
  95 |                 'created_at' => now(),
  96 |                 'updated_at' => now(),
  97 |             ]);
```

Pindah dana Kas/Bank dengan cek saldo dan jurnal cash_bank_transfer — `app/Services/V2/CashBankTransfers.php:96-115`

```text
  96 |             try {
  97 |                 $this->balances->assertSufficient($fromCode, (string) $amount);
  98 |             } catch (\InvalidArgumentException $exception) {
  99 |                 throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
 100 |             }
 101 | 
 102 |             $description = mb_substr(
 103 |                 'Pindah dana '.$fromCode.' ke '.$toCode.' oleh pengguna #'.$actor.': '.$reason,
 104 |                 0,
 105 |                 255
 106 |             );
 107 |             $now = now();
 108 |             $journalId = DB::table('journal_entries')->insertGetId([
 109 |                 'reference_type' => 'cash_bank_transfer',
 110 |                 'reference_id' => null,
 111 |                 'description' => $description,
 112 |                 'transaction_date' => $date,
 113 |                 'created_at' => $now,
 114 |                 'updated_at' => $now,
 115 |             ]);
```

Baris jurnal seimbang DR akun tujuan / CR akun sumber — `app/Services/V2/CashBankTransfers.php:116-130`

```text
 116 |             foreach ([
 117 |                 ['account' => $accounts[$toCode], 'debit' => (string) $amount, 'credit' => '0.00'],
 118 |                 ['account' => $accounts[$fromCode], 'debit' => '0.00', 'credit' => (string) $amount],
 119 |             ] as $line) {
 120 |                 DB::table('journal_entry_lines')->insert([
 121 |                     'journal_entry_id' => $journalId,
 122 |                     'account_code' => $line['account']->account_code,
 123 |                     'account_name' => $line['account']->account_name,
 124 |                     'debit' => $line['debit'],
 125 |                     'credit' => $line['credit'],
 126 |                     'created_at' => $now,
 127 |                     'updated_at' => $now,
 128 |                 ]);
 129 |             }
 130 | 
```

