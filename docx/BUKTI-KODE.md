# Bukti kode langsung

Dokumen ini adalah tampilan alur dari kode working tree, bukan spesifikasi yang harus diikuti kode. Jika berbeda, kode menang. Setiap langkah mencatat pelaku, pemicu, guard, perubahan status/data, cabang gagal, dan bukti path:baris. Kutipan kode dibaca langsung ketika build; tidak ditulis ulang sebagai pseudocode.

Nomor di kiri adalah baris sumber asli, termasuk baris kosong. Jangan edit keluaran ini.

## 03-akses-master-awal — Akses akun, persiapan master, impor dan saldo awal — jejak sumber V2

### AM01 — Masuk dan keluar sesi

Auth::attempt mensyaratkan users.is_active; berhasil meregenerasi sesi, menyimpan pasangan id/auth_version, menghapus intended URL dan menuju pos.index (/). Gagal kembali ke login tanpa password. POST /logout (auth) logout, invalidasi sesi dan regenerasi token. Alias admin dipetakan ke email statis dalam sumber, bukan pencarian username umum.

Validasi identitas dan limiter — `app/Http/Controllers/AuthController.php:128-145`

```text
 128 |         $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());
 129 | 
 130 |         if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
 131 |             $seconds = RateLimiter::availableIn($throttleKey);
 132 | 
 133 |             return redirect()->route('login')->withErrors([
 134 |                 'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
 135 |             ])->withInput($request->except('password'));
 136 |         }
 137 | 
 138 |         $validator = Validator::make($request->all(), [
 139 |             'email' => 'required|string|max:255',
 140 |             'password' => 'required|string',
 141 |         ]);
 142 |         $validator->after(function ($validator) use ($request) {
 143 |             $identifier = (string) $request->input('email');
 144 |             if ($identifier !== 'admin' && ! filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
 145 |                 $validator->errors()->add('email', 'Nama pengguna atau email tidak dikenal.');
```

Akun aktif dan cap sesi — `app/Http/Controllers/AuthController.php:160-171`

```text
 160 |         $credentials['is_active'] = true;
 161 |         if (Auth::attempt($credentials, $request->boolean('remember'))) {
 162 |             $request->session()->regenerate();
 163 |             $request->session()->put('v2_auth_stamp', [(int) Auth::id(), (int) Auth::user()->auth_version]);
 164 |             RateLimiter::clear($throttleKey);
 165 |             $request->session()->forget('url.intended');
 166 | 
 167 |             return redirect()->route('pos.index');
 168 |         }
 169 | 
 170 |         RateLimiter::hit($throttleKey, 60);
 171 | 
```

Logout dan invalidasi — `app/Http/Controllers/AuthController.php:177-183`

```text
 177 |     public function logout(Request $request)
 178 |     {
 179 |         Auth::logout();
 180 |         $request->session()->invalidate();
 181 |         $request->session()->regenerateToken();
 182 | 
 183 |         return redirect()->route('login');
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

Peran aktif dan requireRole — `app/Services/V2/Operations.php:34-48`

```text
  34 |     public function role(int $actor): string
  35 |     {
  36 |         $role = DB::table('v2_user_roles')->join('users', 'users.id', '=', 'v2_user_roles.user_id')->where('v2_user_roles.user_id', $actor)->where('users.is_active', true)->value('v2_user_roles.role');
  37 |         if (! is_string($role) || ! in_array($role, ['admin', 'cashier'], true)) {
  38 |             abort(403);
  39 |         }
  40 | 
  41 |         return $role;
  42 |     }
  43 | 
  44 |     public function requireRole(int $actor, array $roles): void
  45 |     {
  46 |         if (! in_array($this->role($actor), $roles, true)) {
  47 |             abort(403);
  48 |         }
```

Registrasi middleware web — `bootstrap/app.php:14-16`

```text
  14 |     ->withMiddleware(function (Middleware $middleware): void {
  15 |         $middleware->web(append: [\App\Http\Middleware\EnsureActiveAccount::class, \App\Http\Middleware\V2Cutover::class]);
  16 |     })
```

### AM03 — Batasi arsip legacy saat cutover

Untuk jalur legacy lain yang sudah terautentikasi, hanya admin dengan metode GET lolos; kasir atau metode tulis ditolak 403 oleh server. Root diteruskan; PosController::index mendelegasikan ke workspace bila enabled. Tidak menyatakan cutover sedang aktif pada instalasi ini.

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

### AM04 — Baca dan buat akun login

Daftar memakai users LEFT JOIN v2_user_roles tanpa hash dan respons no-store. Pembuatan mengunci role, memeriksa ulang admin aktif dan email lower(trim) unik; membuat users dengan password hash/auth_version=1, role dan audit create secara atomik, respons 201. Email bentrok menjadi validasi. Akun login bukan v2_parties(kind=employee).

Validasi dan recheck aktor — `app/Http/Controllers/V2/AccountController.php:65-82`

```text
  65 |             'name' => ['required', 'string', 'max:255'],
  66 |             'email' => ['required', 'string', 'max:255', 'email'],
  67 |             'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
  68 |             'role' => ['required', 'string', 'in:admin,cashier'],
  69 |             'is_active' => ['required', 'boolean'],
  70 |         ]);
  71 | 
  72 |         $normalizedEmail = strtolower(trim($validated['email']));
  73 | 
  74 |         try {
  75 |             return DB::transaction(function () use ($actorId, $validated, $normalizedEmail) {
  76 |                 // Lock ALL v2_user_roles rows in stable user_id order BEFORE fetching actor/target
  77 |                 DB::table('v2_user_roles')->orderBy('user_id')->lockForUpdate()->get();
  78 | 
  79 |                 // Recheck actor own role row under transaction lock
  80 |                 $actor = DB::table('users')->where('id', $actorId)->lockForUpdate()->first();
  81 |                 $actorRole = DB::table('v2_user_roles')->where('user_id', $actorId)->value('role');
  82 |                 if (!$actor || !$actor->is_active || $actorRole !== 'admin') {
```

Pembuatan user dan role — `app/Http/Controllers/V2/AccountController.php:91-108`

```text
  91 |                 $now = now();
  92 |                 $userId = DB::table('users')->insertGetId([
  93 |                     'name' => $validated['name'],
  94 |                     'email' => $normalizedEmail,
  95 |                     'password' => Hash::make($validated['password']),
  96 |                     'is_active' => (bool) $validated['is_active'],
  97 |                     'auth_version' => 1,
  98 |                     'remember_token' => Str::random(10),
  99 |                     'created_at' => $now,
 100 |                     'updated_at' => $now,
 101 |                 ]);
 102 | 
 103 |                 DB::table('v2_user_roles')->insert([
 104 |                     'user_id' => $userId,
 105 |                     'role' => $validated['role'],
 106 |                     'created_at' => $now,
 107 |                     'updated_at' => $now,
 108 |                 ]);
```

Audit pembuatan akun — `app/Http/Controllers/V2/AccountController.php:110-126`

```text
 110 |                 // Audit log: NO passwords/hash/secrets/PII
 111 |                 DB::table('v2_account_audits')->insert([
 112 |                     'actor_id' => $actorId,
 113 |                     'target_id' => $userId,
 114 |                     'action' => 'create',
 115 |                     'changes' => json_encode([
 116 |                         'changed_fields' => ['name', 'email', 'role', 'is_active', 'password'],
 117 |                         'role' => $validated['role'],
 118 |                         'is_active' => (bool) $validated['is_active'],
 119 |                     ]),
 120 |                     'created_at' => $now,
 121 |                 ]);
 122 | 
 123 |                 $account = [
 124 |                     'id' => (int) $userId,
 125 |                     'name' => (string) $validated['name'],
 126 |                     'email' => (string) $normalizedEmail,
```

### AM05 — Ubah akun tanpa memutus kendali admin

Dilarang nonaktif/demosi diri sendiri dan admin aktif terakhir (422). Shift target berstatus open melarang perubahan role/nonaktif. Email harus unik. users dan role diperbarui, audit update dibuat. HANYA perubahan role/status/password menaikkan auth_version, memutar remember_token serta menghapus sesi DB jika tabel sessions ada; perubahan nama/email saja tidak menaikkan versi.

Larangan self-demotion dan hitung admin — `app/Http/Controllers/V2/AccountController.php:185-202`

```text
 185 |                 // Rule: Forbid self disable / demotion
 186 |                 if ($actorId === $targetId) {
 187 |                     if ($newRole !== 'admin' || !$newIsActive) {
 188 |                         abort(422, 'Akun sendiri tidak dapat dinonaktifkan atau dilepas dari Admin.');
 189 |                     }
 190 |                 }
 191 | 
 192 |                 // Rule: Always preserve at least 1 active admin
 193 |                 $isTargetActiveAdmin = ($targetRole === 'admin' && (bool) $target->is_active);
 194 |                 $willTargetBeActiveAdmin = ($newRole === 'admin' && $newIsActive);
 195 | 
 196 |                 if ($isTargetActiveAdmin && !$willTargetBeActiveAdmin) {
 197 |                     $activeAdminCount = DB::table('v2_user_roles')
 198 |                         ->join('users', 'users.id', '=', 'v2_user_roles.user_id')
 199 |                         ->where('v2_user_roles.role', 'admin')
 200 |                         ->where('users.is_active', true)
 201 |                         ->count();
 202 | 
```

Admin terakhir dan shift terbuka — `app/Http/Controllers/V2/AccountController.php:203-219`

```text
 203 |                     if ($activeAdminCount <= 1) {
 204 |                         abort(422, 'Admin aktif terakhir harus dipertahankan.');
 205 |                     }
 206 |                 }
 207 | 
 208 |                 // Rule: Guard downgrade/deactivation while target open v2_shifts to avoid stranded shift
 209 |                 $hasOpenShift = DB::table('v2_shifts')
 210 |                     ->where('user_id', $targetId)
 211 |                     ->where('status', 'open')
 212 |                     ->exists();
 213 | 
 214 |                 if ($hasOpenShift) {
 215 |                     $isRoleChange = ($targetRole !== $newRole);
 216 |                     $isDeactivation = ((bool) $target->is_active && !$newIsActive);
 217 |                     if ($isRoleChange || $isDeactivation) {
 218 |                         abort(422, 'Tutup shift akun ini dahulu sebelum mengganti role atau menonaktifkannya.');
 219 |                     }
```

Invalidasi versi kondisional dan update — `app/Http/Controllers/V2/AccountController.php:264-279`

```text
 264 |                 // On change role/status/password increment auth_version, rotate remember_token, delete sessions
 265 |                 if ($roleChanged || $statusChanged || $passwordChanged) {
 266 |                     $userUpdates['auth_version'] = (int) $target->auth_version + 1;
 267 |                     $userUpdates['remember_token'] = Str::random(60);
 268 | 
 269 |                     if (Schema::hasTable('sessions')) {
 270 |                         DB::table('sessions')->where('user_id', $targetId)->delete();
 271 |                     }
 272 |                 }
 273 | 
 274 |                 DB::table('users')->where('id', $targetId)->update($userUpdates);
 275 | 
 276 |                 DB::table('v2_user_roles')->updateOrInsert(
 277 |                     ['user_id' => $targetId],
 278 |                     ['role' => $newRole, 'updated_at' => $now, 'created_at' => $now]
 279 |                 );
```

### AM06 — Baca dan buat master dasar

GET membaca maksimal 100 record terbaru per kind, produk disertai supplier. POST atomik membuat produk stok 0, kendaraan status active atau pihak is_active=true; produk melalui resolusi satuan/kategori dan supplier. Customer price_tier hanya retail/contractor; contractor_price positif atau null. Employee hanya master pihak, tidak membuat users/role atau akses login.

Admin, kind dan keunikan — `app/Services/V2/MasterData.php:95-112`

```text
  95 |     public function store(int $actor, string $kind, array $data): int
  96 |     {
  97 |         $this->requireAdmin($actor);
  98 |         if (! in_array($kind, self::KINDS, true)) {
  99 |             throw new RuntimeException("Kind {$kind} tidak valid.");
 100 |         }
 101 | 
 102 |         return DB::transaction(function () use ($kind, $data) {
 103 |             $row = $this->validateRow($kind, $data);
 104 |             [$table, $keyColumn, $keyLabel] = $this->keySpec($kind);
 105 |             $keyValue = $row[$keyColumn];
 106 | 
 107 |             if (DB::table($table)->where($keyColumn, $keyValue)->lockForUpdate()->exists()) {
 108 |                 throw new RuntimeException("{$keyLabel} {$keyValue} sudah terdaftar.");
 109 |             }
 110 | 
 111 |             $now = now();
 112 |             if ($kind === 'product') {
```

Efek create master tanpa stok awal — `app/Services/V2/MasterData.php:113-128`

```text
 113 |                 $row = app(MasterRelations::class)->productAttributes($data, $row);
 114 |                 $payload = $row + ['stock_quantity' => '0.0000', 'is_active' => true]; // zero product stock
 115 |             } elseif ($kind === 'vehicle') {
 116 |                 $payload = $row + ['status' => 'active'];
 117 |             } else {
 118 |                 $payload = $row + ['kind' => $kind, 'is_active' => true];
 119 |             }
 120 |             $payload['created_at'] = $now;
 121 |             $payload['updated_at'] = $now;
 122 | 
 123 |             $id = DB::table($table)->insertGetId($payload);
 124 |             if ($kind === 'product') {
 125 |                 app(MasterRelations::class)->syncProductSuppliers($id, $data);
 126 |             }
 127 | 
 128 |             return $id;
```

Kolom produk dan kendaraan — `app/Services/V2/MasterData.php:240-257`

```text
 240 |     private function validateRow(string $kind, array $data): array
 241 |     {
 242 |         if ($kind === 'product') {
 243 |             return [
 244 |                 'sku' => $this->text($data['sku'] ?? '', 'sku', true),
 245 |                 'name' => $this->text($data['name'] ?? '', 'name', true),
 246 |                 'unit' => isset($data['unit_id']) && $data['unit_id'] !== '' ? '' : $this->text($data['unit'] ?? '', 'unit', true),
 247 |                 'category' => isset($data['category_id']) && $data['category_id'] !== '' ? '' : ($this->text($data['category'] ?? 'Umum', 'category') ?: 'Umum'),
 248 |                 'cost_price' => $this->decimal($data['cost_price'] ?? '0', 'cost_price', 4),
 249 |                 'selling_price' => $this->decimal($data['selling_price'] ?? '0', 'selling_price', 2),
 250 |                 'contractor_price' => $this->optionalPrice($data['contractor_price'] ?? null, 'contractor_price'),
 251 |                 'min_stock' => $this->decimal($data['min_stock'] ?? '0', 'min_stock', 4),
 252 |             ];
 253 |         }
 254 | 
 255 |         if ($kind === 'vehicle') {
 256 |             return [
 257 |                 'license_plate' => $this->text($data['license_plate'] ?? '', 'license_plate', true),
```

### AM07 — Ubah atau arsip master tanpa hapus riwayat

PUT tidak mengganti kind/stok/status arsip; field numerik tertentu, notes dan tier yang tidak dikirim dipertahankan. HPP produk tidak boleh berubah bila stok nonzero atau ada stock_movements. PATCH hanya menonaktifkan (kendaraan status inactive, lainnya is_active=false), tanpa hard delete; tidak ada guard larangan arsip berdasar pemakaian di metode ini.

HPP terkunci dan pembaruan — `app/Services/V2/MasterData.php:173-186`

```text
 173 |             // HPP produk yang sudah punya stok/riwayat hanya boleh berubah lewat
 174 |             // penerimaan atau saldo awal, supaya nilai persediaan tidak direvaluasi diam-diam.
 175 |             if ($kind === 'product'
 176 |                 && $row['cost_price'] !== $this->decimal($current->cost_price ?? '0', 'cost_price', 4)
 177 |                 && $this->costLockedIds([$id]) !== []) {
 178 |                 throw new RuntimeException('HPP tidak dapat diubah: produk sudah punya stok atau riwayat pergerakan. Ubah HPP lewat penerimaan/saldo awal.');
 179 |             }
 180 | 
 181 |             if ($kind === 'product') {
 182 |                 $row = app(MasterRelations::class)->productAttributes($data, $row, $current);
 183 |                 app(MasterRelations::class)->syncProductSuppliers($id, $data);
 184 |             }
 185 |             $row['updated_at'] = now();
 186 |             DB::table($table)->where('id', $id)->update($row);
```

Arsip bukan delete — `app/Services/V2/MasterData.php:191-205`

```text
 191 |     public function archive(int $actor, string $kind, int $id): void
 192 |     {
 193 |         $this->requireAdmin($actor);
 194 |         $this->assertKind($kind);
 195 | 
 196 |         DB::transaction(function () use ($kind, $id): void {
 197 |             [$table] = $this->keySpec($kind);
 198 |             if (! $this->rowQuery($kind, $id)->lockForUpdate()->exists()) {
 199 |                 throw new RuntimeException('Data tidak ditemukan.');
 200 |             }
 201 | 
 202 |             $payload = $kind === 'vehicle'
 203 |                 ? ['status' => 'inactive', 'updated_at' => now()]
 204 |                 : ['is_active' => false, 'updated_at' => now()];
 205 |             DB::table($table)->where('id', $id)->update($payload);
```

Sumber lock HPP — `app/Services/V2/MasterData.php:210-220`

```text
 210 |     public function costLockedIds(array $productIds): array
 211 |     {
 212 |         $ids = array_values(array_unique(array_map('intval', $productIds)));
 213 |         if ($ids === []) {
 214 |             return [];
 215 |         }
 216 | 
 217 |         $withStock = DB::table('products')->whereIn('id', $ids)->where('stock_quantity', '<>', '0')->pluck('id')->all();
 218 |         $withHistory = DB::table('stock_movements')->whereIn('product_id', $ids)->distinct()->pluck('product_id')->all();
 219 | 
 220 |         return array_values(array_unique(array_map('intval', array_merge($withStock, $withHistory))));
```

### AM08 — Kelola satuan dan kategori

Membuat product_options aktif; rename memperbarui teks unit/category pada products yang terhubung ID. Arsip hanya is_active=false; pemakaian produk tidak menghalangi arsip dan relasi lama tidak dihapus. Jenis/ID tidak cocok ditolak. Pembuatan atau penggantian pilihan produk baru perlu opsi aktif, sedangkan tautan produk yang sudah sama tetap sah setelah opsi diarsip.

Create relasi admin — `app/Services/V2/MasterRelations.php:31-42`

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
```

Rename cascade dan arsip — `app/Services/V2/MasterRelations.php:61-78`

```text
  61 |             $this->rowQuery($kind, $id)->update($row + ['updated_at' => now()]);
  62 | 
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
```

Pengecualian tautan opsi yang telah ada — `app/Services/V2/MasterRelations.php:118-124`

```text
 118 |             // Master yang sudah tertaut ke produk ini tetap sah walau diarsip; pilihan baru wajib aktif.
 119 |             $alreadyLinked = $option && $current !== null && (int) ($current->{$field} ?? 0) === (int) $option->id;
 120 |             if (! $option || (! $option->is_active && ! $alreadyLinked)) {
 121 |                 throw new RuntimeException("Master {$kind} tidak aktif atau tidak ditemukan.");
 122 |             }
 123 |             $row[$field] = (int) $option->id;
 124 |             $row[$kind] = $this->text($option->name, $kind, $kind === 'unit' ? 32 : 50);
```

### AM09 — Kelola sopir terpisah dari akun login

Create/PUT menyimpan v2_drivers, internal memakai nama employee; saat PUT menuju external employee_id dikosongkan. PATCH menonaktifkan sopir, tidak menghapus. Driver resolver menolak sopir nonaktif/pegawai internal nonaktif. Tidak ada pembuatan users/role pada alur ini.

Jenis sopir dan employee — `app/Services/V2/MasterRelations.php:179-196`

```text
 179 |     private function attributes(string $kind, array $data): array
 180 |     {
 181 |         if ($kind !== 'driver') {
 182 |             return ['type' => $kind, 'name' => $this->text($data['name'] ?? '', 'name', $kind === 'unit' ? 32 : 50)];
 183 |         }
 184 |         $driverKind = $data['kind'] ?? null;
 185 |         if (! in_array($driverKind, ['internal', 'external'], true)) {
 186 |             throw new RuntimeException('Jenis sopir harus internal atau external.');
 187 |         }
 188 |         $employeeId = $this->nullableId($data['employee_id'] ?? null, 'employee_id');
 189 |         if ($driverKind === 'external' && $employeeId !== null) {
 190 |             throw new RuntimeException('Sopir eksternal tidak boleh terhubung ke pegawai.');
 191 |         }
 192 |         $name = $driverKind === 'internal'
 193 |             ? $this->employee($employeeId ?? 0)->name
 194 |             : $this->text($data['name'] ?? '', 'name', 255);
 195 | 
 196 |         return [
```

Employee aktif dan keunikan sopir — `app/Services/V2/MasterRelations.php:208-225`

```text
 208 |             throw new RuntimeException('Pegawai sopir tidak aktif atau tidak ditemukan.');
 209 |         }
 210 | 
 211 |         return $employee;
 212 |     }
 213 | 
 214 |     private function assertUnique(string $kind, array $row, ?int $except = null): void
 215 |     {
 216 |         $query = DB::table($kind === 'driver' ? 'v2_drivers' : 'product_options');
 217 |         if ($except !== null) {
 218 |             $query->where('id', '<>', $except);
 219 |         }
 220 |         if ($kind === 'driver') {
 221 |             if ((clone $query)->where('code', $row['code'])->exists()) {
 222 |                 throw new RuntimeException('Kode sopir sudah terdaftar.');
 223 |             }
 224 |             if ($row['employee_id'] !== null && $query->where('employee_id', $row['employee_id'])->exists()) {
 225 |                 throw new RuntimeException('Pegawai sudah terdaftar sebagai sopir.');
```

Resolver sopir memeriksa status — `app/Services/V2/MasterRelations.php:83-96`

```text
  83 |     public function driver(int $id): object
  84 |     {
  85 |         $this->requireTransaction();
  86 |         $driver = DB::table('v2_drivers')->where('id', $id)->where('is_active', true)->lockForUpdate()->first();
  87 |         if (! $driver) {
  88 |             throw new RuntimeException('Sopir tidak aktif atau tidak ditemukan.');
  89 |         }
  90 |         if ($driver->kind === 'internal') {
  91 |             $driver->name = $this->employee((int) $driver->employee_id)->name;
  92 |         } elseif ($driver->kind !== 'external' || $driver->employee_id !== null) {
  93 |             throw new RuntimeException('Relasi sopir tidak valid.');
  94 |         }
  95 | 
  96 |         return $driver;
```

### AM10 — Validasi tautan produk ke opsi dan supplier

Teks opsi baru dapat membuat product_options saat write saja; pilihan baru harus aktif, tautan opsi lama boleh tetap walau arsip. Supplier baru harus kind supplier aktif; supplier terarsip yang sudah tertaut boleh dipertahankan. Primary harus anggota daftar. Jika field supplier tidak dikirim, tautan tidak diubah; replace menghapus lalu membuat ulang link, hanya-primary memperbarui is_primary. Kesalahan membatalkan transaksi master.

Resolusi opsi aktif atau tautan lama — `app/Services/V2/MasterRelations.php:105-122`

```text
 105 |             if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
 106 |                 $id = $this->id($data[$field], $field);
 107 |                 $option = DB::table('product_options')->where('id', $id)->where('type', $kind)->lockForUpdate()->first();
 108 |             } else {
 109 |                 $name = $this->text($row[$kind], $kind, $kind === 'unit' ? 32 : 50);
 110 |                 $option = DB::table('product_options')->where('type', $kind)->where('name', $name)->lockForUpdate()->first();
 111 |                 if (! $option) {
 112 |                     DB::table('product_options')->insertOrIgnore([
 113 |                         'type' => $kind, 'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
 114 |                     ]);
 115 |                     $option = DB::table('product_options')->where('type', $kind)->where('name', $name)->lockForUpdate()->first();
 116 |                 }
 117 |             }
 118 |             // Master yang sudah tertaut ke produk ini tetap sah walau diarsip; pilihan baru wajib aktif.
 119 |             $alreadyLinked = $option && $current !== null && (int) ($current->{$field} ?? 0) === (int) $option->id;
 120 |             if (! $option || (! $option->is_active && ! $alreadyLinked)) {
 121 |                 throw new RuntimeException("Master {$kind} tidak aktif atau tidak ditemukan.");
 122 |             }
```

Primary dan supplier aktif — `app/Services/V2/MasterRelations.php:145-159`

```text
 145 |         $oldPrimary = $existing->first(fn (object $link): bool => (bool) $link->is_primary);
 146 |         $primary = array_key_exists('primary_supplier_id', $data)
 147 |             ? $this->nullableId($data['primary_supplier_id'], 'primary_supplier_id')
 148 |             : ($oldPrimary && in_array((int) $oldPrimary->supplier_id, $ids, true) ? (int) $oldPrimary->supplier_id : null);
 149 |         if ($primary !== null && ! in_array($primary, $ids, true)) {
 150 |             throw new RuntimeException('Supplier utama harus termasuk daftar supplier produk.');
 151 |         }
 152 |         $validateIds = $replace ? $ids : ($primary === null ? [] : [$primary]);
 153 |         $suppliers = DB::table('v2_parties')->whereIn('id', $validateIds)->where('kind', 'supplier')
 154 |             ->orderBy('id')->lockForUpdate()->get(['id', 'is_active']);
 155 |         // Link yang sudah ada ke supplier terarsip dipertahankan; link baru wajib ke supplier aktif.
 156 |         $linkedIds = $existing->pluck('supplier_id')->map(fn ($id): int => (int) $id)->all();
 157 |         $usable = $suppliers->every(fn (object $supplier): bool => (bool) $supplier->is_active || in_array((int) $supplier->id, $linkedIds, true));
 158 |         if ($suppliers->pluck('id')->map(fn ($id): int => (int) $id)->all() !== $validateIds || ! $usable) {
 159 |             throw new RuntimeException('Supplier tidak aktif atau tidak ditemukan.');
```

Penggantian tautan versus primary saja — `app/Services/V2/MasterRelations.php:162-175`

```text
 162 |         if ($replace) {
 163 |             DB::table('v2_product_suppliers')->where('product_id', $productId)->delete();
 164 |             foreach ($ids as $supplierId) {
 165 |                 DB::table('v2_product_suppliers')->insert([
 166 |                     'product_id' => $productId, 'supplier_id' => $supplierId, 'is_primary' => $supplierId === $primary,
 167 |                     'created_at' => now(), 'updated_at' => now(),
 168 |                 ]);
 169 |             }
 170 |         } else {
 171 |             DB::table('v2_product_suppliers')->where('product_id', $productId)->update(['is_primary' => false, 'updated_at' => now()]);
 172 |             if ($primary !== null) {
 173 |                 DB::table('v2_product_suppliers')->where('product_id', $productId)->where('supplier_id', $primary)
 174 |                     ->update(['is_primary' => true, 'updated_at' => now()]);
 175 |             }
```

### AM11 — Cari atau buat pelanggan dari kasir

Search di bawah 2 karakter kosong; hanya customer aktif, nama mengandung term atau kode berawalan term, default maksimal 8. Resolve memakai kecocokan nama case-insensitive: customer aktif dikembalikan, terarsip ditolak 422; bila belum ada dibuat kode CUS berurutan aktif. Tidak menyentuh uang/stok/jurnal dan tidak membuka master umum untuk kasir.

Rute pencarian dan resolve serta izin — `app/Http/Controllers/V2/WorkspaceController.php:259-272`

```text
 259 |     public function searchCustomers(Request $request): JsonResponse
 260 |     {
 261 |         $this->operations->requireRole($request->user()->id, ['admin', 'cashier']);
 262 |         $data = $request->validate(['q' => ['required', 'string', 'max:255']]);
 263 | 
 264 |         return response()->json(['results' => app(CustomerLookup::class)->search($data['q'])]);
 265 |     }
 266 | 
 267 |     /** Memakai pelanggan yang sudah ada, atau membuatnya bila nama itu belum terdaftar. */
 268 |     public function resolveCustomer(Request $request): JsonResponse
 269 |     {
 270 |         $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
 271 | 
 272 |         return $this->run(fn () => app(CustomerLookup::class)->resolve($request->user()->id, $data['name']));
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

Kolom template per kind — `app/Services/V2/MasterData.php:50-56`

```text
  50 |     public function columns(string $kind): array
  51 |     {
  52 |         return match ($kind) {
  53 |             'product' => ['sku', 'name', 'unit', 'category', 'cost_price', 'selling_price', 'min_stock'],
  54 |             'customer', 'supplier', 'employee' => ['code', 'name', 'notes'],
  55 |             'vehicle' => ['license_plate', 'vehicle_type'],
  56 |             default => throw new RuntimeException("Kind {$kind} tidak valid."),
```

Gerbang dan pembuatan template — `app/Services/V2/MasterData.php:354-360`

```text
 354 |     public function template(int $actor, string $kind): string
 355 |     {
 356 |         $this->requireAdmin($actor);
 357 |         $this->assertXlsxKind($kind);
 358 |         $cols = $this->columns($kind);
 359 | 
 360 |         return $this->buildMinimalXlsx($cols);
```

Larangan XLSX relation kind — `app/Services/V2/MasterData.php:501-506`

```text
 501 |     private function assertXlsxKind(string $kind): void
 502 |     {
 503 |         if (in_array($kind, self::RELATION_KINDS, true)) {
 504 |             throw new RuntimeException("XLSX tidak tersedia untuk master {$kind}.");
 505 |         }
 506 |         $this->assertKind($kind);
```

### AM13 — Pratinjau impor dan simpan batch tervalidasi

Validasi kolom/angka dan duplikasi key dalam file maupun DB. Gagal memberi errors dan batch_id=null tanpa menulis master; berhasil menyimpan v2_import_batches status preview, rows normalisasi dan actor_id, lalu mengembalikan batch_id/count serta maksimal 100 baris. Preview bukan read-only total: ada tulis batch, namun tidak membuat product_options/master.

Upload dan admin — `app/Http/Controllers/V2/WorkspaceController.php:371-376`

```text
 371 |     public function importPreview(Request $request, string $kind): JsonResponse
 372 |     {
 373 |         $this->operations->requireRole($request->user()->id, ['admin']);
 374 |         $request->validate(['file' => ['required', 'file', 'max:2048']]);
 375 | 
 376 |         return $this->run(fn () => app(MasterData::class)->preview($request->user()->id, $kind, $request->file('file')->getRealPath()));
```

Bentrok DB menolak batch — `app/Services/V2/MasterData.php:414-431`

```text
 414 |         // Unik terhadap data yang sudah ada: batch tidak boleh dibuat bila ada bentrok.
 415 |         if (empty($errors)) {
 416 |             $existing = $this->existingKeys($kind, array_keys($uniqueKeys));
 417 |             if (! empty($existing)) {
 418 |                 $errors[] = 'Data sudah terdaftar di database: '.implode(', ', $existing).'.';
 419 |             }
 420 |         }
 421 | 
 422 |         if (! empty($errors)) {
 423 |             return [
 424 |                 'batch_id' => null,
 425 |                 'count' => $rowCount,
 426 |                 'rows' => array_slice($parsedRows, 0, 100),
 427 |                 'errors' => $errors,
 428 |             ];
 429 |         }
 430 | 
 431 |         // Simpan preview batch ke database
```

Persistensi batch preview — `app/Services/V2/MasterData.php:432-448`

```text
 432 |         $batchId = DB::table('v2_import_batches')->insertGetId([
 433 |             'actor_id' => $actor,
 434 |             'kind' => $kind,
 435 |             'status' => 'preview',
 436 |             'row_count' => count($validatedRecords),
 437 |             'rows' => json_encode($validatedRecords, JSON_THROW_ON_ERROR),
 438 |             'committed_at' => null,
 439 |             'created_at' => now(),
 440 |             'updated_at' => now(),
 441 |         ]);
 442 | 
 443 |         return [
 444 |             'batch_id' => $batchId,
 445 |             'count' => count($validatedRecords),
 446 |             'rows' => array_slice($validatedRecords, 0, 100),
 447 |             'errors' => [],
 448 |         ];
```

### AM14 — Commit batch impor milik sendiri

Batch committed dikembalikan idempoten tanpa insert ulang. Batch belum committed dibaca rows/kind lalu tiap baris masuk MasterData::store untuk revalidasi aturan terbaru. Seluruh insert dan perubahan status committed satu transaksi; bentrok/perubahan opsi setelah preview menggagalkan seluruh commit. Produk impor tetap stok 0, tanpa jurnal/faktur awal.

Kunci batch dan kepemilikan — `app/Services/V2/MasterData.php:451-468`

```text
 451 |     public function commit(int $actor, int $batchId): array
 452 |     {
 453 |         $this->requireAdmin($actor);
 454 | 
 455 |         return DB::transaction(function () use ($actor, $batchId) {
 456 |             $batch = DB::table('v2_import_batches')->where('id', $batchId)->lockForUpdate()->first();
 457 |             if (! $batch) {
 458 |                 throw new RuntimeException('Batch import tidak ditemukan.');
 459 |             }
 460 |             if ((int) $batch->actor_id !== $actor) {
 461 |                 abort(403);
 462 |             }
 463 | 
 464 |             // Idempotent check
 465 |             if ($batch->status === 'committed') {
 466 |                 return [
 467 |                     'batch_id' => $batch->id,
 468 |                     'kind' => $batch->kind,
```

Commit melalui store secara atomik — `app/Services/V2/MasterData.php:475-489`

```text
 475 |             $kind = $batch->kind;
 476 |             $this->assertXlsxKind($kind);
 477 |             $rows = json_decode($batch->rows, true, 512, JSON_THROW_ON_ERROR);
 478 | 
 479 |             // Revalidasi + insert per baris lewat satu jalur (store): aturan sama dengan form manual,
 480 |             // dan kegagalan satu baris membatalkan seluruh transaksi komit.
 481 |             foreach ($rows as $row) {
 482 |                 $this->store($actor, $kind, $row);
 483 |             }
 484 | 
 485 |             DB::table('v2_import_batches')->where('id', $batchId)->update([
 486 |                 'status' => 'committed',
 487 |                 'committed_at' => now(),
 488 |                 'updated_at' => now(),
 489 |             ]);
```

Aturan insert produk stok nol — `app/Services/V2/MasterData.php:111-125`

```text
 111 |             $now = now();
 112 |             if ($kind === 'product') {
 113 |                 $row = app(MasterRelations::class)->productAttributes($data, $row);
 114 |                 $payload = $row + ['stock_quantity' => '0.0000', 'is_active' => true]; // zero product stock
 115 |             } elseif ($kind === 'vehicle') {
 116 |                 $payload = $row + ['status' => 'active'];
 117 |             } else {
 118 |                 $payload = $row + ['kind' => $kind, 'is_active' => true];
 119 |             }
 120 |             $payload['created_at'] = $now;
 121 |             $payload['updated_at'] = $now;
 122 | 
 123 |             $id = DB::table($table)->insertGetId($payload);
 124 |             if ($kind === 'product') {
 125 |                 app(MasterRelations::class)->syncProductSuppliers($id, $data);
```

### AM15 — Pratinjau saldo awal sistem bersih

Tanpa mutasi DB: normalisasi desimal, tanggal valid, referensi max50, kas/bank/qty/HPP nonnegatif; produk harus ada dan tidak duplikat. Piutang butuh customer aktif, utang supplier aktif, nomor faktur unik termasuk antar kedua kelompok, amount positif dan due_date valid. Ringkasan serta rancangan jurnal mengimbangi selisih aset-utang ke ekuitas 3101; pembukaan kosong ditolak.

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

Jika batch sudah ada, key DAN hash payload sama mengembalikan idempotent_ok; selain itu ditolak sebagai immutable. Posting membuat v2_opening_batches, jurnal seimbang melalui Ledger, memperbarui stok/HPP dan stock_movements, membuat faktur receivable/payable open paid=0 tanpa sale/receipt palsu, menautkan journal_entry_id. Akun COA terpakai harus aktif/ada; gagal membatalkan transaksi. Tidak tersedia edit/reset saldo awal di routes/v2.php.

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

## 02-penerimaan-pembayaran — Penerimaan supplier dan pembayaran invoice V2 — alur dari current working tree (analisis statis)

### RP01 — Buka tagihan dan penerimaan

Tagihan 100 terakhir dibaca tanpa pembatas pemilik; last_payment_id ialah pembayaran terakhir selain rejected. Pembayaran 50 terakhir dibatasi requested_by untuk kasir. Admin membaca 50 penerimaan final dan 50 draf draft/cancelled; final memiliki audit konfirmasi melalui receipt_id. Tidak mengubah data transaksi.

Peran aktif yang benar-benar diotorisasi — `app/Services/V2/Operations.php:34-49`

```text
  34 |     public function role(int $actor): string
  35 |     {
  36 |         $role = DB::table('v2_user_roles')->join('users', 'users.id', '=', 'v2_user_roles.user_id')->where('v2_user_roles.user_id', $actor)->where('users.is_active', true)->value('v2_user_roles.role');
  37 |         if (! is_string($role) || ! in_array($role, ['admin', 'cashier'], true)) {
  38 |             abort(403);
  39 |         }
  40 | 
  41 |         return $role;
  42 |     }
  43 | 
  44 |     public function requireRole(int $actor, array $roles): void
  45 |     {
  46 |         if (! in_array($this->role($actor), $roles, true)) {
  47 |             abort(403);
  48 |         }
  49 |     }
```

Redirect dan tagihan tanpa filter pemilik — `app/Http/Controllers/V2/WorkspaceController.php:34-48`

```text
  34 |     public function index(Request $request)
  35 |     {
  36 |         $role = $this->operations->role($request->user()->id);
  37 |         if (config('v2.enabled') && $request->routeIs('v2.index')) {
  38 |             return redirect()->route('pos.index', $request->query());
  39 |         }
  40 | 
  41 |         $shifts = DB::table('v2_shifts as s')->leftJoin('users as u', 'u.id', '=', 's.user_id')
  42 |             ->when($role !== 'admin', fn ($q) => $q->where('s.user_id', $request->user()->id))
  43 |             ->orderByDesc('s.id')->limit(30)->get(['s.*', 'u.name as user_name']);
  44 |         $invoices = DB::table('v2_invoices as i')->leftJoin('v2_parties as p', 'p.id', '=', 'i.party_id')
  45 |             ->orderByDesc('i.id')->limit(100)->select(['i.*', 'p.name as party_name'])
  46 |             // Pembayaran terakhir (bukan ditolak) saat halaman dibuat: penjaga entri dobel di form bayar.
  47 |             ->selectSub(fn ($q) => $q->from('v2_payments')->whereColumn('v2_payments.invoice_id', 'i.id')->where('v2_payments.status', '<>', 'rejected')->selectRaw('coalesce(max(v2_payments.id), 0)'), 'last_payment_id')
  48 |             ->get();
```

Riwayat final dan draf dipisahkan — `app/Http/Controllers/V2/WorkspaceController.php:74-91`

```text
  74 |         $receiptHistory = $role === 'admin' ? DB::table('inventory_receipts')->leftJoin('users as void_user', 'void_user.id', '=', 'inventory_receipts.voided_by')
  75 |             ->orderByDesc('inventory_receipts.receipt_date')->orderByDesc('inventory_receipts.id')->limit(50)
  76 |             ->get(['inventory_receipts.*', 'void_user.name as voided_by_name']) : collect();
  77 |         // Dokumen yang sudah diposting selalu final. Draf disimpan terpisah dan belum masuk stok/utang.
  78 |         $receiptDrafts = $role === 'admin' && Schema::hasTable('v2_receipt_drafts')
  79 |             ? DB::table('v2_receipt_drafts as d')->join('v2_parties as p', 'p.id', '=', 'd.supplier_id')
  80 |                 ->leftJoin('users as u', 'u.id', '=', 'd.created_by')
  81 |                 ->whereIn('d.status', ['draft', 'cancelled'])->orderByDesc('d.id')->limit(50)
  82 |                 ->get(['d.*', 'p.name as supplier_name', 'u.name as author_name'])
  83 |             : collect();
  84 |         foreach ($receiptDrafts as $draft) {
  85 |             $draft->items = json_decode($draft->items, true, 512, JSON_THROW_ON_ERROR);
  86 |         }
  87 |         $draftProducts = $receiptDrafts->isEmpty() ? collect() : DB::table('products')
  88 |             ->whereIn('id', $receiptDrafts->flatMap(fn ($draft) => array_column($draft->items, 'product_id'))->unique())
  89 |             ->get(['id', 'name', 'sku', 'unit', 'cost_price', 'stock_quantity'])->keyBy('id');
  90 |         $receiptFinalAudit = $role === 'admin' && Schema::hasTable('v2_receipt_drafts')
  91 |             ? DB::table('v2_receipt_drafts as d')->leftJoin('users as u', 'u.id', '=', 'd.confirmed_by')
```

### RP02 — Cari barang supplier

Hanya relasi produk-supplier milik id tersebut, supplier aktif berjenis supplier dan produk aktif. Nama/SKU dicari dengan LIKE, hasil diurut nama, default 30 dibatasi 1..50; total dan results dikembalikan. Supplier tidak cocok menghasilkan daftar kosong, bukan penerimaan atau jurnal.

Rute pencarian ke service — `app/Http/Controllers/V2/WorkspaceController.php:418-423`

```text
 418 |     public function supplierProducts(Request $request, int $id): JsonResponse
 419 |     {
 420 |         $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
 421 | 
 422 |         return $this->run(fn () => app(SupplyTrips::class)->productsFor($request->user()->id, $id, (string) ($data['q'] ?? '')));
 423 |     }
```

Akses admin dan produk aktif tertaut — `app/Services/V2/SupplyTrips.php:395-410`

```text
 395 |     public function productsFor(int $actor, int $supplierId, string $term = '', int $limit = 30): array
 396 |     {
 397 |         $this->requireAdmin($actor);
 398 | 
 399 |         $query = DB::table('v2_product_suppliers')
 400 |             ->join('products', 'products.id', '=', 'v2_product_suppliers.product_id')
 401 |             ->join('v2_parties', 'v2_parties.id', '=', 'v2_product_suppliers.supplier_id')
 402 |             ->where('v2_product_suppliers.supplier_id', $supplierId)
 403 |             ->where('v2_parties.kind', 'supplier')
 404 |             ->where('v2_parties.is_active', true)
 405 |             ->where('products.is_active', true);
 406 | 
 407 |         $term = trim($term);
 408 |         if ($term !== '') {
 409 |             $query->where(fn ($match) => $match->where('products.name', 'like', '%'.$term.'%')->orWhere('products.sku', 'like', '%'.$term.'%'));
 410 |         }
```

Jumlah hasil dan payload pilihan — `app/Services/V2/SupplyTrips.php:412-425`

```text
 412 |         $total = (clone $query)->count();
 413 |         $rows = $query->orderBy('products.name')->limit(max(1, min($limit, 50)))
 414 |             ->get(['products.id', 'products.sku', 'products.name', 'products.unit', 'products.stock_quantity', 'products.cost_price']);
 415 | 
 416 |         return [
 417 |             'total' => $total,
 418 |             'results' => $rows->map(fn ($row) => [
 419 |                 'id' => (int) $row->id,
 420 |                 'sku' => (string) $row->sku,
 421 |                 'name' => (string) $row->name,
 422 |                 'unit' => (string) $row->unit,
 423 |                 'stock' => (string) $row->stock_quantity,
 424 |                 'cost' => (string) $row->cost_price,
 425 |             ])->all(),
```

### RP03 — Simpan draf baru

Transaksi mengunci user, menyimpan snapshot di v2_receipt_drafts status draft/version 1. Kunci per created_by dengan hash sama mengembalikan draf yang tersimpan; hash berbeda gagal validasi. Tidak membuat receipt final, utang, stok, HPP, kas/bank, atau jurnal. Validasi gagal membatalkan transaksi.

Endpoint receipts menyimpan draf — `app/Http/Controllers/V2/WorkspaceController.php:384-394`

```text
 384 |     public function receive(Request $request): JsonResponse
 385 |     {
 386 |         $this->operations->requireRole($request->user()->id, ['admin']);
 387 | 
 388 |         return $this->run(fn () => app(ReceiptDrafts::class)->save($request->user()->id, $request->all()));
 389 |     }
 390 | 
 391 |     public function updateReceiptDraft(Request $request, int $id): JsonResponse
 392 |     {
 393 |         return $this->run(fn () => app(ReceiptDrafts::class)->save($request->user()->id, $request->all(), $id));
 394 |     }
```

Idempotensi dan insert draf — `app/Services/V2/ReceiptDrafts.php:24-41`

```text
  24 |             if ($id === null) {
  25 |                 $key = $data['idempotency_key'] ?? null;
  26 |                 if (! is_string($key) || preg_match('/^[\x20-\x7E]{1,100}$/', $key) !== 1) {
  27 |                     $this->invalid('Kunci idempotensi wajib 1..100 karakter ASCII.');
  28 |                 }
  29 |                 $row = DB::table('v2_receipt_drafts')->where('created_by', $actor)->where('idempotency_key', $key)->lockForUpdate()->first();
  30 |                 if ($row !== null) {
  31 |                     if (! hash_equals($row->request_hash, $hash)) {
  32 |                         $this->invalid('Kunci idempotensi sudah dipakai untuk muatan berbeda.');
  33 |                     }
  34 | 
  35 |                     return $this->result($row);
  36 |                 }
  37 |                 $id = DB::table('v2_receipt_drafts')->insertGetId($snapshot + ['created_by' => $actor, 'idempotency_key' => $key, 'request_hash' => $hash, 'status' => 'draft', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
  38 |             } else {
  39 |                 $row = $this->locked($id);
  40 |                 $this->editable($row, $data);
  41 |                 DB::table('v2_receipt_drafts')->where('id', $id)->update($snapshot + ['version' => $row->version + 1, 'updated_at' => now()]);
```

Produk aktif, relasi supplier dan nilai — `app/Services/V2/ReceiptDrafts.php:123-140`

```text
 123 |         $products = DB::table('products')->whereIn('id', $ids)->get(['id', 'is_active'])->keyBy('id');
 124 |         $links = DB::table('v2_product_suppliers')->where('supplier_id', $supplier)->whereIn('product_id', $ids)->pluck('product_id')->flip();
 125 |         foreach ($data['items'] as $row) {
 126 |             $product = (int) $row['product_id'];
 127 |             if (! isset($products[$product]) || ! $products[$product]->is_active) {
 128 |                 $this->invalid('Produk #'.$product.' tidak ditemukan atau tidak aktif.');
 129 |             }
 130 |             if (isset($items[$product])) {
 131 |                 $this->invalid('Produk duplikat; gabungkan menjadi satu baris.');
 132 |             }
 133 |             if (! $links->has($product)) {
 134 |                 $this->invalid('Produk #'.$product.' belum terhubung ke supplier yang dipilih. Atur relasi produk-supplier pada master terlebih dahulu.');
 135 |             }
 136 |             $quantity = $this->decimal($row['quantity'] ?? null);
 137 |             $cost = $this->decimal($row['unit_cost'] ?? null);
 138 |             if (! $quantity->isPositive() || $cost->isNegative()) {
 139 |                 $this->invalid('Jumlah harus positif dan harga tidak boleh negatif.');
 140 |             }
```

### RP04 — Ubah draf

Mengunci user serta baris draf; memperbarui snapshot dan menaikkan version. Tidak mengubah request_hash/idempotency_key awal. Status cancelled/confirmed dan versi usang ditolak. Tidak ada efek ke stok, HPP, utang atau jurnal.

Update snapshot dan version — `app/Services/V2/ReceiptDrafts.php:38-44`

```text
  38 |             } else {
  39 |                 $row = $this->locked($id);
  40 |                 $this->editable($row, $data);
  41 |                 DB::table('v2_receipt_drafts')->where('id', $id)->update($snapshot + ['version' => $row->version + 1, 'updated_at' => now()]);
  42 |             }
  43 | 
  44 |             return $this->result(DB::table('v2_receipt_drafts')->where('id', $id)->first());
```

Kunci draf dan guard status/versi — `app/Services/V2/ReceiptDrafts.php:174-191`

```text
 174 |     private function locked(int $id): object
 175 |     {
 176 |         $row = DB::table('v2_receipt_drafts')->where('id', $id)->lockForUpdate()->first();
 177 |         if ($row === null) {
 178 |             $this->invalid('Draf penerimaan tidak ditemukan.');
 179 |         }
 180 | 
 181 |         return $row;
 182 |     }
 183 | 
 184 |     private function editable(object $row, array $input): void
 185 |     {
 186 |         if ($row->status !== 'draft') {
 187 |             $this->invalid('Hanya draf yang dapat diubah; penerimaan final terkunci.');
 188 |         }
 189 |         if (filter_var($input['version'] ?? null, FILTER_VALIDATE_INT) !== (int) $row->version) {
 190 |             $this->invalid('Versi draf telah berubah. Muat ulang sebelum melanjutkan.');
 191 |         }
```

UI mengirim snapshot/version ke endpoint draf — `resources/views/v2/sprint2.blade.php:787-799`

```text
 787 |         const payload = {
 788 |             supplier_id: receipt.elements.supplier_id.value,
 789 |             receipt_date: receipt.elements.receipt_date.value,
 790 |             supplier_invoice_number: receipt.elements.supplier_invoice_number.value.trim() || null,
 791 |             confirm_duplicate: receipt.dataset.confirmDuplicate === '1',
 792 |             items: items,
 793 |             idempotency_key: receipt.dataset.key,
 794 |             version: receipt.dataset.draftVersion || null,
 795 |             notes: receipt.dataset.draftNotes || null
 796 |         };
 797 |         receipt._lastError = null;
 798 |         const url = receipt.dataset.draftId ? draftUpdateUrl.replace(/\/0$/, '/' + receipt.dataset.draftId) : @json(route('v2.receipts.store'));
 799 |         if (await send(receipt, url, payload, false, null, true)) {
```

### RP05 — Batalkan draf

Status cancelled, version bertambah dan cancelled_by/cancelled_at/cancel_reason dicatat; draf tidak dihapus. Confirmed tidak dapat dibatalkan. Stok, HPP, receipt final, utang, kas/bank dan jurnal tidak berubah.

Idempoten cancelled dan audit pembatalan — `app/Services/V2/ReceiptDrafts.php:76-91`

```text
  76 |     public function cancel(int $actor, int $id, array $input): array
  77 |     {
  78 |         $this->requireAdmin($actor);
  79 | 
  80 |         return DB::transaction(function () use ($actor, $id, $input) {
  81 |             DB::table('users')->where('id', $actor)->lockForUpdate()->first();
  82 |             $row = $this->locked($id);
  83 |             if ($row->status === 'cancelled') {
  84 |                 return $this->result($row);
  85 |             }
  86 |             $this->editable($row, $input);
  87 |             $reason = $input['reason'] ?? null;
  88 |             if (! is_string($reason) || mb_strlen(trim($reason)) < 5 || mb_strlen(trim($reason)) > 255) {
  89 |                 $this->invalid('Alasan pembatalan wajib 5..255 karakter.');
  90 |             }
  91 |             DB::table('v2_receipt_drafts')->where('id', $id)->update(['status' => 'cancelled', 'version' => $row->version + 1, 'cancelled_by' => $actor, 'cancelled_at' => now(), 'cancel_reason' => trim($reason), 'updated_at' => now()]);
```

Hanya draft dengan versi cocok — `app/Services/V2/ReceiptDrafts.php:184-191`

```text
 184 |     private function editable(object $row, array $input): void
 185 |     {
 186 |         if ($row->status !== 'draft') {
 187 |             $this->invalid('Hanya draf yang dapat diubah; penerimaan final terkunci.');
 188 |         }
 189 |         if (filter_var($input['version'] ?? null, FILTER_VALIDATE_INT) !== (int) $row->version) {
 190 |             $this->invalid('Versi draf telah berubah. Muat ulang sebelum melanjutkan.');
 191 |         }
```

### RP06 — Periksa konfirmasi dan nota ganda

SupplyTrips::receive dipanggil dengan kunci draft-receipt-{id}. Nota opsional yang sama untuk supplier sama, lower/trim dan hanya receipt belum void, memicu duplicate_receipt bila confirm_duplicate tidak benar; transaksi gagal sehingga draf tetap draft. Kirim ulang konfirmasi khusus hanya melewati guard nota ganda, bukan guard lain. Replay v2_requests dengan hash sama mengembalikan hasil lama.

Persetujuan final wajib pada endpoint — `app/Http/Controllers/V2/WorkspaceController.php:396-401`

```text
 396 |     public function confirmReceiptDraft(Request $request, int $id): JsonResponse
 397 |     {
 398 |         $this->operations->requireRole($request->user()->id, ['admin']);
 399 |         $request->validate(['confirm_final' => ['required', 'accepted']]);
 400 | 
 401 |         return $this->run(fn () => app(ReceiptDrafts::class)->confirm($request->user()->id, $id, $request->all()));
```

Replay confirmed dan snapshot tersimpan — `app/Services/V2/ReceiptDrafts.php:53-68`

```text
  53 |         return DB::transaction(function () use ($actor, $id, $input) {
  54 |             DB::table('users')->where('id', $actor)->lockForUpdate()->first();
  55 |             $row = $this->locked($id);
  56 |             if ($row->status === 'confirmed') {
  57 |                 return $this->result($row);
  58 |             }
  59 |             $this->editable($row, $input);
  60 |             $posted = $this->supplyTrips->receive($actor, [
  61 |                 'supplier_id' => (int) $row->supplier_id,
  62 |                 'receipt_date' => $row->receipt_date,
  63 |                 'notes' => $row->notes,
  64 |                 'supplier_invoice_number' => $row->supplier_invoice_number,
  65 |                 'items' => json_decode($row->items, true, 512, JSON_THROW_ON_ERROR),
  66 |                 'idempotency_key' => 'draft-receipt-'.$id,
  67 |                 'confirm_duplicate' => $input['confirm_duplicate'] ?? false,
  68 |             ]);
```

Guard nota supplier ganda pada konfirmasi — `app/Services/V2/SupplyTrips.php:102-115`

```text
 102 |             $supplier = DB::table('v2_parties')->where('id', $supplierId)->lockForUpdate()->first();
 103 |             if (! $supplier || $supplier->kind !== 'supplier' || ! $supplier->is_active) {
 104 |                 $this->invalid('Supplier tidak ditemukan atau tidak aktif.');
 105 |             }
 106 |             // Penjaga entri dobel: nota supplier yang sama sudah tercatat (penerimaan yang dibatalkan tidak dihitung).
 107 |             if ($nota !== null && ! $confirmed) {
 108 |                 $same = DB::table('inventory_receipts as r')->join('v2_invoices as i', 'i.receipt_id', '=', 'r.id')
 109 |                     ->where('i.party_id', $supplierId)->whereNull('r.voided_at')
 110 |                     ->whereRaw('lower(trim(r.supplier_invoice_number)) = ?', [mb_strtolower($nota)])
 111 |                     ->orderByDesc('r.id')->first(['r.receipt_number', 'r.receipt_date', 'r.total_amount']);
 112 |                 if ($same) {
 113 |                     throw ValidationException::withMessages(['duplicate_receipt' => 'Nota '.$nota.' dari '.$supplier->name.' sudah dicatat sebagai '.$same->receipt_number.' tanggal '.Carbon::parse($same->receipt_date)->locale('id')->translatedFormat('j M Y').' ('.Money::display($same->total_amount).'). Bila memang nota berbeda, tekan tombol Ya, tetap konfirmasi.']);
 114 |                 }
 115 |             }
```

### RP07 — Posting penerimaan final

Dalam transaksi: receipt purchase bernomor RCV dan item dibuat; stok naik, HPP rata-rata tertimbang diperbarui, mutasi v2_receipt masuk. Ledger debit Persediaan/kredit Utang supplier memakai tanggal penerimaan; receipt ditautkan jurnal. Invoice payable AP terhubung receipt_id dibuat amount=total, paid=0,status=open; v2_requests menyimpan replay. Draf menjadi confirmed, version naik, receipt_id/confirmed_by/confirmed_at disimpan. Tidak ada kas/bank keluar. Kegagalan posting menggulung seluruh konfirmasi.

Stok dan HPP tertimbang serta awal mutasi — `app/Services/V2/SupplyTrips.php:187-204`

```text
 187 |                 $product = $products[$row['product_id']];
 188 |                 $oldStock = Money::value((string) $product->stock_quantity, 4);
 189 |                 $oldCost = Money::value((string) $product->cost_price, 4);
 190 |                 $newStock = $oldStock->plus($row['quantity']);
 191 |                 $value = $oldStock->multipliedBy($oldCost)
 192 |                     ->plus($row['quantity']->multipliedBy($row['unit_cost']));
 193 |                 $newCost = $value->dividedBy($newStock, 4, RoundingMode::HalfUp);
 194 | 
 195 |                 DB::table('products')->where('id', $row['product_id'])->update([
 196 |                     'stock_quantity' => (string) $newStock->toScale(4, RoundingMode::HalfUp),
 197 |                     'cost_price' => (string) $newCost,
 198 |                     'updated_at' => $now,
 199 |                 ]);
 200 | 
 201 |                 DB::table('stock_movements')->insert([
 202 |                     'product_id' => $row['product_id'],
 203 |                     'reference_type' => 'v2_receipt',
 204 |                     'reference_id' => $receiptId,
```

Jurnal penerimaan dan invoice payable terhubung — `app/Services/V2/SupplyTrips.php:213-230`

```text
 213 |             $journalId = Ledger::post('v2_receipt', $receiptId, 'Penerimaan barang '.$date.' dari '.$supplier->name, [
 214 |                 ['account_code' => self::INVENTORY, 'debit' => $this->str($total), 'credit' => '0.00'],
 215 |                 ['account_code' => self::PAYABLE, 'debit' => '0.00', 'credit' => $this->str($total)],
 216 |             ], $date);
 217 | 
 218 |             DB::table('inventory_receipts')->where('id', $receiptId)->update([
 219 |                 'journal_entry_id' => $journalId,
 220 |                 'updated_at' => $now,
 221 |             ]);
 222 | 
 223 |             $invoiceId = (int) DB::table('v2_invoices')->insertGetId([
 224 |                 'direction' => 'payable',
 225 |                 'party_id' => $supplierId,
 226 |                 'sale_id' => null,
 227 |                 'receipt_id' => $receiptId,
 228 |                 'number' => 'AP-'.str_pad((string) $receiptId, 6, '0', STR_PAD_LEFT),
 229 |                 'amount' => $this->str($total),
 230 |                 'paid' => '0.00',
```

Konfirmasi atomik dan audit draf — `app/Services/V2/ReceiptDrafts.php:59-71`

```text
  59 |             $this->editable($row, $input);
  60 |             $posted = $this->supplyTrips->receive($actor, [
  61 |                 'supplier_id' => (int) $row->supplier_id,
  62 |                 'receipt_date' => $row->receipt_date,
  63 |                 'notes' => $row->notes,
  64 |                 'supplier_invoice_number' => $row->supplier_invoice_number,
  65 |                 'items' => json_decode($row->items, true, 512, JSON_THROW_ON_ERROR),
  66 |                 'idempotency_key' => 'draft-receipt-'.$id,
  67 |                 'confirm_duplicate' => $input['confirm_duplicate'] ?? false,
  68 |             ]);
  69 |             DB::table('v2_receipt_drafts')->where('id', $id)->update(['status' => 'confirmed', 'version' => $row->version + 1, 'receipt_id' => $posted['receipt_id'], 'confirmed_by' => $actor, 'confirmed_at' => now(), 'updated_at' => now()]);
  70 | 
  71 |             return $this->result($this->locked($id));
```

### RP08 — Tolak void penerimaan final

Receipt yang ada selalu gagal validasi: penerimaan sudah dikonfirmasi dan terkunci, gunakan koreksi/retur. Tidak ada reversal jurnal, pemulihan HPP/stok, pembatalan invoice, update voided_at, atau transisi kembali ke draft. Komentar lama sebelum method masih menjelaskan reversal, tetapi badan method tidak melaksanakannya.

Endpoint lama dipertahankan tetapi ditolak — `app/Http/Controllers/V2/WorkspaceController.php:409-414`

```text
 409 |     /** Jalur lama tetap ditolak di server: penerimaan yang sudah diposting terkunci. */
 410 |     public function voidReceipt(Request $request, int $id): JsonResponse
 411 |     {
 412 |         $this->operations->requireRole($request->user()->id, ['admin']);
 413 | 
 414 |         return $this->run(fn () => app(SupplyTrips::class)->voidReceipt($request->user()->id, $id, $request->all()));
```

Badan voidReceipt selalu menolak receipt yang ada — `app/Services/V2/SupplyTrips.php:437-451`

```text
 437 |     public function voidReceipt(int $actor, int $receiptId, array $input): array
 438 |     {
 439 |         $this->requireAdmin($actor);
 440 |         $receipt = DB::table('inventory_receipts')->where('id', $receiptId)->first();
 441 |         abort_unless($receipt, 404);
 442 | 
 443 |         $this->invalid('Penerimaan sudah dikonfirmasi dan terkunci. Pembatalan biasa tidak diizinkan; gunakan proses koreksi atau retur.');
 444 |     }
 445 | 
 446 |     private function requireAdmin(int $actor): void
 447 |     {
 448 |         $role = DB::table('v2_user_roles')->where('user_id', $actor)->value('role');
 449 |         if ($role !== 'admin') {
 450 |             abort(403);
 451 |         }
```

### RP09 — Validasi pembayaran dan entri ganda

Kunci pembayaran dicari global: pemohon harus actor sama (lainnya 403), hash invoice/amount/method/reference sama mengembalikan payment lama; isi berbeda ditolak. Arah invoice harus receivable/payable; main untuk receivable ditolak. Jika seen_payment_id ada dan tanpa confirm_duplicate, pembayaran id lebih baru selain rejected memicu duplicate_payment. Tanpa seen_payment_id guard dilewati. Nilai baru harus <= amount-paid dan sisa positif. Tidak ada pembatas kepemilikan invoice; cabang lanjutan bergantung direction/method/role.

Validasi request pembayaran — `app/Http/Controllers/V2/WorkspaceController.php:321-329`

```text
 321 |     public function collect(Request $request, int $id): JsonResponse
 322 |     {
 323 |         $data = $request->validate(['amount' => ['required', 'string', 'max:30'], 'method' => ['required', Rule::in(['cash', 'transfer', 'main'])], 'idempotency_key' => ['required', 'string', 'min:8', 'max:100'], 'reference' => ['nullable', 'string', 'max:255'], 'seen_payment_id' => ['nullable', 'integer', 'min:0'], 'confirm_duplicate' => ['nullable', 'boolean']]);
 324 | 
 325 |         return $this->run(function () use ($request, $id, $data): array {
 326 |             $seen = isset($data['seen_payment_id']) ? (int) $data['seen_payment_id'] : null;
 327 |             $paymentId = $this->operations->collect($request->user()->id, $id, $data['amount'], $data['method'], $data['idempotency_key'], $data['reference'] ?? null, $seen, (bool) ($data['confirm_duplicate'] ?? false));
 328 | 
 329 |             return ['id' => $paymentId, 'status' => (string) DB::table('v2_payments')->where('id', $paymentId)->value('status')];
```

Kunci invoice dan replay pembayaran — `app/Services/V2/Operations.php:650-667`

```text
 650 |         return DB::transaction(function () use ($actor, $invoiceId, $value, $method, $key, $reference, $seenPaymentId, $confirmed) {
 651 |             $invoice = DB::table('v2_invoices')->where('id', $invoiceId)->lockForUpdate()->first();
 652 |             if (! $invoice) {
 653 |                 $this->invalid('Tagihan tidak ditemukan.');
 654 |             }
 655 |             $requestHash = hash('sha256', json_encode([$invoiceId, $this->str($value), $method, $reference], JSON_THROW_ON_ERROR));
 656 |             $existing = DB::table('v2_payments')->where('idempotency_key', $key)->first();
 657 |             if ($existing) {
 658 |                 abort_unless((int) $existing->requested_by === $actor, 403);
 659 |                 if ($existing->request_hash !== $requestHash) {
 660 |                     $this->invalid('Kunci pembayaran sudah dipakai untuk isi berbeda.');
 661 |                 }
 662 | 
 663 |                 return (int) $existing->id;
 664 |             }
 665 |             if (! in_array($invoice->direction, ['receivable', 'payable'], true)) {
 666 |                 $this->invalid('Arah tagihan tidak valid.');
 667 |             }
```

Guard perubahan pembayaran sejak form dibuka — `app/Services/V2/Operations.php:1055-1066`

```text
1055 |     private function guardNewerPayments(int $invoiceId, int $seenPaymentId): void
1056 |     {
1057 |         $newer = DB::table('v2_payments as pm')->leftJoin('users as u', 'u.id', '=', 'pm.requested_by')
1058 |             ->where('pm.invoice_id', $invoiceId)->where('pm.id', '>', $seenPaymentId)->where('pm.status', '<>', 'rejected')
1059 |             ->orderByDesc('pm.id')->get(['pm.amount', 'pm.method', 'pm.status', 'pm.created_at', 'u.name']);
1060 |         if ($newer->isEmpty()) {
1061 |             return;
1062 |         }
1063 |         $last = $newer->first();
1064 |         $what = Money::display($last->amount).' ('.$this->methodLabel((string) $last->method).($last->status === 'posted' ? '' : ', menunggu').')';
1065 |         $more = $newer->count() > 1 ? ' dan '.($newer->count() - 1).' pembayaran lain' : '';
1066 |         throw ValidationException::withMessages(['duplicate_payment' => 'Sejak form ini dibuka, '.($last->name ?? 'pengguna lain').' sudah mencatat pembayaran '.$what.' pukul '.Carbon::parse($last->created_at)->format('H:i').$more.'. Pastikan ini bukan uang yang sama. Bila memang pembayaran lain, tekan tombol Ya, tetap catat.']);
```

### RP10 — Terima piutang tunai

Payment posted dengan shift_id sendiri dan approved_by actor dibuat; jurnal debit Kas/kredit Piutang, gerakan laci direction=in/kind=collection. paid invoice bertambah, status paid bila lunas atau open bila sebagian; bila sale_id ada, status penjualan dihitung paid/partial/unpaid. Gagal akun/jurnal atau validasi menggulung transaksi.

Shift dan payment posted tunai — `app/Services/V2/Operations.php:731-748`

```text
 731 |             $shift = $this->openShiftFor($actor, true);
 732 |             $paymentId = DB::table('v2_payments')->insertGetId([
 733 |                 'invoice_id' => $invoiceId,
 734 |                 'shift_id' => (int) $shift->id,
 735 |                 'amount' => $this->str($value),
 736 |                 'method' => 'cash',
 737 |                 'status' => 'posted',
 738 |                 'requested_by' => $actor,
 739 |                 'approved_by' => $actor,
 740 |                 'reference' => $reference,
 741 |                 'idempotency_key' => $key,
 742 |                 'request_hash' => $requestHash,
 743 |                 'journal_entry_id' => null,
 744 |                 'created_at' => now(),
 745 |                 'updated_at' => now(),
 746 |             ]);
 747 | 
 748 |             $entryId = Ledger::post('v2_payment', $paymentId, 'Penerimaan '.$invoice->number, [
```

Jurnal kas dan uang laci masuk — `app/Services/V2/Operations.php:748-764`

```text
 748 |             $entryId = Ledger::post('v2_payment', $paymentId, 'Penerimaan '.$invoice->number, [
 749 |                 ['account_code' => self::CASH, 'debit' => $this->str($value), 'credit' => '0.00'],
 750 |                 ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($value)],
 751 |             ]);
 752 |             DB::table('v2_payments')->where('id', $paymentId)->update(['journal_entry_id' => $entryId, 'updated_at' => now()]);
 753 | 
 754 |             DB::table('v2_shift_movements')->insert([
 755 |                 'shift_id' => (int) $shift->id,
 756 |                 'direction' => 'in',
 757 |                 'kind' => 'collection',
 758 |                 'amount' => $this->str($value),
 759 |                 'reference_type' => 'v2_payment',
 760 |                 'reference_id' => $paymentId,
 761 |                 'note' => 'Penerimaan '.$invoice->number,
 762 |                 'created_at' => now(),
 763 |                 'updated_at' => now(),
 764 |             ]);
```

Saldo invoice dan status sale diperbarui — `app/Services/V2/Operations.php:766-774`

```text
 766 |             DB::table('v2_invoices')->where('id', $invoiceId)->update([
 767 |                 'paid' => $this->str(Money::value((string) $invoice->paid, 2)->plus($value)),
 768 |                 'status' => $value->isEqualTo($remaining) ? 'paid' : 'open',
 769 |                 'updated_at' => now(),
 770 |             ]);
 771 | 
 772 |             if ($invoice->sale_id !== null) {
 773 |                 DB::table('v2_sales')->where('id', $invoice->sale_id)->update(['status' => $this->saleStatus(Money::value((string) $invoice->paid)->plus($value), Money::value((string) $invoice->amount)), 'updated_at' => now()]);
 774 |             }
```

### RP11 — Catat transfer pelanggan menunggu

Payment pending, shift_id/approved_by/journal_entry_id null. Belum menambah paid invoice, belum mengubah sale atau laci/bank/jurnal. Menunggu approve admin atau reject admin atas payment_id yang sama; meski pencatat admin, transfer piutang tetap pending.

Guard sumber dan sisa tagihan — `app/Services/V2/Operations.php:668-681`

```text
 668 |             if ($invoice->direction === 'receivable' && $method === 'main') {
 669 |                 $this->invalid('Kas Utama hanya untuk pembayaran ke supplier.');
 670 |             }
 671 |             if ($seenPaymentId !== null && ! $confirmed) {
 672 |                 $this->guardNewerPayments($invoiceId, $seenPaymentId);
 673 |             }
 674 | 
 675 |             $remaining = Money::value((string) $invoice->amount, 2)->minus(Money::value((string) $invoice->paid, 2));
 676 |             if (! $remaining->isPositive()) {
 677 |                 $this->invalid('Tagihan sudah lunas.');
 678 |             }
 679 |             if ($value->isGreaterThan($remaining)) {
 680 |                 $this->invalid('Nominal melebihi sisa tagihan.');
 681 |             }
```

Transfer receivable selalu pending — `app/Services/V2/Operations.php:713-729`

```text
 713 |             if ($method === 'transfer') {
 714 |                 return DB::table('v2_payments')->insertGetId([
 715 |                     'invoice_id' => $invoiceId,
 716 |                     'shift_id' => null,
 717 |                     'amount' => $this->str($value),
 718 |                     'method' => 'transfer',
 719 |                     'status' => 'pending',
 720 |                     'requested_by' => $actor,
 721 |                     'approved_by' => null,
 722 |                     'reference' => $reference,
 723 |                     'idempotency_key' => $key,
 724 |                     'request_hash' => $requestHash,
 725 |                     'journal_entry_id' => null,
 726 |                     'created_at' => now(),
 727 |                     'updated_at' => now(),
 728 |                 ]);
 729 |             }
```

### RP12 — Ajukan pembayaran supplier

Payment proposed disimpan dengan requested_by actor, approved_by/journal null. currentShiftId juga disimpan untuk main/transfer bila ada, tetapi tidak berarti laci dipakai. Kasir tidak memanggil postPayable; paid invoice, kas/bank, jurnal dan gerakan laci belum berubah. Saldo buku besar/laci baru diperiksa saat posting; pengajuan tidak mereservasi sisa utang.

Sumber dana dan proposed — `app/Services/V2/Operations.php:683-700`

```text
 683 |             if ($invoice->direction === 'payable') {
 684 |                 // Sumber dana: main = Kas Utama (tidak menyentuh laci), cash = laci shift pemohon, transfer = Bank Utama.
 685 |                 $shiftId = $this->currentShiftId($actor);
 686 |                 if ($method === 'cash' && $shiftId === null) {
 687 |                     $this->invalid('Sumber Laci shift butuh shift kasir terbuka; pilih Kas Utama atau Bank Utama.');
 688 |                 }
 689 | 
 690 |                 // Kasir mengajukan; admin membayar langsung tercatat (tunai dari laci shift-nya, transfer dari bank).
 691 |                 $paymentId = DB::table('v2_payments')->insertGetId([
 692 |                     'invoice_id' => $invoiceId,
 693 |                     'shift_id' => $shiftId,
 694 |                     'amount' => $this->str($value),
 695 |                     'method' => $method,
 696 |                     'status' => 'proposed',
 697 |                     'requested_by' => $actor,
 698 |                     'approved_by' => null,
 699 |                     'reference' => $reference,
 700 |                     'idempotency_key' => $key,
```

Admin saja langsung posting setelah insert — `app/Services/V2/Operations.php:700-710`

```text
 700 |                     'idempotency_key' => $key,
 701 |                     'request_hash' => $requestHash,
 702 |                     'journal_entry_id' => null,
 703 |                     'created_at' => now(),
 704 |                     'updated_at' => now(),
 705 |                 ]);
 706 |                 if ($this->role($actor) === 'admin') {
 707 |                     $this->postPayable($actor, $invoice, DB::table('v2_payments')->where('id', $paymentId)->first());
 708 |                 }
 709 | 
 710 |                 return $paymentId;
```

### RP13 — Bayar supplier dari Kas Utama

Payment menjadi posted dengan approved_by admin/journal_id. Jurnal debit Utang supplier/kredit Kas; invoice paid bertambah, status paid bila lunas atau open bila sebagian. Tidak mencatat mutasi laci meskipun shift_id kebetulan tersimpan. Kegagalan saldo/akun membatalkan insert dan posting.

Admin collect langsung postPayable — `app/Services/V2/Operations.php:700-710`

```text
 700 |                     'idempotency_key' => $key,
 701 |                     'request_hash' => $requestHash,
 702 |                     'journal_entry_id' => null,
 703 |                     'created_at' => now(),
 704 |                     'updated_at' => now(),
 705 |                 ]);
 706 |                 if ($this->role($actor) === 'admin') {
 707 |                     $this->postPayable($actor, $invoice, DB::table('v2_payments')->where('id', $paymentId)->first());
 708 |                 }
 709 | 
 710 |                 return $paymentId;
```

main memakai akun kas tanpa laci — `app/Services/V2/Operations.php:861-870`

```text
 861 |         $paymentId = (int) $payment->id;
 862 |         $source = (string) $payment->method;
 863 |         if (! in_array($source, ['cash', 'main', 'transfer'], true)) {
 864 |             $this->invalid('Sumber dana pembayaran tidak valid.');
 865 |         }
 866 |         // Laci shift memakai uang laci kasir; Kas Utama dan Bank Utama memakai saldo toko dan tidak mengubah laci.
 867 |         $drawer = $source === 'cash';
 868 |         $account = $source === 'transfer' ? self::BANK : self::CASH;
 869 |         $label = $source === 'transfer' ? 'Bank Utama' : 'Kas Utama (Tunai)';
 870 |         if ($drawer) {
```

Saldo buku besar tidak boleh negatif — `app/Services/V2/Operations.php:926-937`

```text
 926 |     private function guardAccount(string $code, BigDecimal $delta, ?string $label = null): void
 927 |     {
 928 |         if (! DB::table('chart_of_accounts')->where('account_code', $code)->lockForUpdate()->exists()) {
 929 |             $this->invalid('Akun '.$code.' belum tersedia di bagan akun.');
 930 |         }
 931 |         $balance = Money::value('0');
 932 |         foreach (DB::table('journal_entry_lines')->where('account_code', $code)->get(['debit', 'credit']) as $line) {
 933 |             $balance = $balance->plus((string) $line->debit)->minus((string) $line->credit);
 934 |         }
 935 |         if ($balance->plus($delta)->isNegative()) {
 936 |             $this->invalid('Saldo '.($label ?? 'akun '.$code).' tidak cukup untuk pembayaran keluar.');
 937 |         }
```

### RP14 — Bayar supplier dari laci

Admin langsung memposting: debit Utang supplier/kredit Kas, payment posted, invoice paid bertambah/status open atau paid. Gerakan out/kind=supplier dibuat pada shift PEMOHON; bukan mengambil shift admin penyetuju lain. Kegagalan satu guard menggulung transaksi.

Guard kepemilikan/saldo laci dan jurnal — `app/Services/V2/Operations.php:870-887`

```text
 870 |         if ($drawer) {
 871 |             $drawer = DB::table('v2_shifts')->where('id', $payment->shift_id)->lockForUpdate()->first();
 872 |             if (! $drawer || $drawer->status !== 'open' || (int) $drawer->user_id !== (int) $payment->requested_by) {
 873 |                 $this->invalid('Shift pengajuan tidak terbuka; ajukan ulang.');
 874 |             }
 875 |             $drawerBalance = Money::value((string) $drawer->opening_cash);
 876 |             foreach (DB::table('v2_shift_movements')->where('shift_id', $drawer->id)->get(['direction', 'amount']) as $movement) {
 877 |                 $drawerBalance = $movement->direction === 'in' ? $drawerBalance->plus((string) $movement->amount) : $drawerBalance->minus((string) $movement->amount);
 878 |             }
 879 |             if ($drawerBalance->isLessThan($amount)) {
 880 |                 $this->invalid('Uang tunai laci tidak cukup untuk pembayaran.');
 881 |             }
 882 |         }
 883 |         $this->guardAccount($account, $amount->negated(), $label);
 884 |         $entryId = Ledger::post('v2_payment', $paymentId, 'Pembayaran utang '.$invoice->number, [
 885 |             ['account_code' => self::AP, 'debit' => $this->str($amount), 'credit' => '0.00'],
 886 |             ['account_code' => $account, 'debit' => '0.00', 'credit' => $this->str($amount)],
 887 |         ]);
```

Gerakan supplier keluar di shift pemohon — `app/Services/V2/Operations.php:893-910`

```text
 893 |             $shift = DB::table('v2_shifts')->where('id', $payment->shift_id)->lockForUpdate()->first();
 894 |             if (! $shift || $shift->status !== 'open') {
 895 |                 $this->invalid('Shift pengajuan sudah ditutup; ajukan ulang.');
 896 |             }
 897 |             if ((int) $shift->user_id !== (int) $payment->requested_by) {
 898 |                 $this->invalid('Shift pengajuan bukan milik pemohon.');
 899 |             }
 900 |             DB::table('v2_shift_movements')->insert([
 901 |                 'shift_id' => (int) $shift->id,
 902 |                 'direction' => 'out',
 903 |                 'kind' => 'supplier',
 904 |                 'amount' => $this->str($amount),
 905 |                 'reference_type' => 'v2_payment',
 906 |                 'reference_id' => $paymentId,
 907 |                 'note' => 'Pembayaran utang '.$invoice->number,
 908 |                 'created_at' => now(),
 909 |                 'updated_at' => now(),
 910 |             ]);
```

Payment posted dan invoice diperbarui — `app/Services/V2/Operations.php:913-923`

```text
 913 |         DB::table('v2_payments')->where('id', $paymentId)->update([
 914 |             'status' => 'posted',
 915 |             'approved_by' => $actor,
 916 |             'journal_entry_id' => $entryId,
 917 |             'updated_at' => now(),
 918 |         ]);
 919 |         DB::table('v2_invoices')->where('id', $invoice->id)->update([
 920 |             'paid' => $this->str(Money::value((string) $invoice->paid, 2)->plus($amount)),
 921 |             'status' => $amount->isEqualTo($remaining) ? 'paid' : 'open',
 922 |             'updated_at' => now(),
 923 |         ]);
```

### RP15 — Bayar supplier dari bank

Jurnal debit Utang supplier/kredit Bank, payment posted dengan approved_by admin/journal_id, paid invoice naik dan status open/paid. Tidak ada pending verifikasi kedua atau gerakan laci. Jika saldo/akun tidak cukup transaksi gagal seluruhnya.

Transfer memilih akun bank bukan laci — `app/Services/V2/Operations.php:861-870`

```text
 861 |         $paymentId = (int) $payment->id;
 862 |         $source = (string) $payment->method;
 863 |         if (! in_array($source, ['cash', 'main', 'transfer'], true)) {
 864 |             $this->invalid('Sumber dana pembayaran tidak valid.');
 865 |         }
 866 |         // Laci shift memakai uang laci kasir; Kas Utama dan Bank Utama memakai saldo toko dan tidak mengubah laci.
 867 |         $drawer = $source === 'cash';
 868 |         $account = $source === 'transfer' ? self::BANK : self::CASH;
 869 |         $label = $source === 'transfer' ? 'Bank Utama' : 'Kas Utama (Tunai)';
 870 |         if ($drawer) {
```

Jurnal bayar supplier — `app/Services/V2/Operations.php:883-887`

```text
 883 |         $this->guardAccount($account, $amount->negated(), $label);
 884 |         $entryId = Ledger::post('v2_payment', $paymentId, 'Pembayaran utang '.$invoice->number, [
 885 |             ['account_code' => self::AP, 'debit' => $this->str($amount), 'credit' => '0.00'],
 886 |             ['account_code' => $account, 'debit' => '0.00', 'credit' => $this->str($amount)],
 887 |         ]);
```

Finalisasi payment/invoice dan guard akun — `app/Services/V2/Operations.php:913-930`

```text
 913 |         DB::table('v2_payments')->where('id', $paymentId)->update([
 914 |             'status' => 'posted',
 915 |             'approved_by' => $actor,
 916 |             'journal_entry_id' => $entryId,
 917 |             'updated_at' => now(),
 918 |         ]);
 919 |         DB::table('v2_invoices')->where('id', $invoice->id)->update([
 920 |             'paid' => $this->str(Money::value((string) $invoice->paid, 2)->plus($amount)),
 921 |             'status' => $amount->isEqualTo($remaining) ? 'paid' : 'open',
 922 |             'updated_at' => now(),
 923 |         ]);
 924 |     }
 925 | 
 926 |     private function guardAccount(string $code, BigDecimal $delta, ?string $label = null): void
 927 |     {
 928 |         if (! DB::table('chart_of_accounts')->where('account_code', $code)->lockForUpdate()->exists()) {
 929 |             $this->invalid('Akun '.$code.' belum tersedia di bagan akun.');
 930 |         }
```

### RP16 — Setujui transfer piutang

Jurnal debit Bank/kredit Piutang dibuat, payment menjadi posted, approved_by actor dan journal_id diisi. paid invoice bertambah/status open atau paid; sale_id terkait memperbarui status sale. Tidak memerlukan shift atau gerakan laci. Nominal yang sudah terlampaui pembayaran lain menggagalkan approve tanpa mengubah pending.

Status dan sisa diperiksa ulang — `app/Services/V2/Operations.php:794-809`

```text
 794 |             $payment = DB::table('v2_payments')->where('id', $paymentId)->lockForUpdate()->first();
 795 | 
 796 |             if ($payment->status === 'posted') {
 797 |                 return;
 798 |             }
 799 |             if (! in_array($payment->status, ['pending', 'proposed'], true)) {
 800 |                 $this->invalid('Status pembayaran tidak dapat disetujui.');
 801 |             }
 802 |             $amount = Money::value((string) $payment->amount, 2);
 803 |             if (! $amount->isPositive()) {
 804 |                 $this->invalid('Nominal pembayaran tidak valid.');
 805 |             }
 806 |             $remaining = Money::value((string) $invoice->amount, 2)->minus(Money::value((string) $invoice->paid, 2));
 807 |             if ($amount->isGreaterThan($remaining)) {
 808 |                 $this->invalid('Nominal melebihi sisa tagihan.');
 809 |             }
```

Cabang verifikasi transfer receivable — `app/Services/V2/Operations.php:811-825`

```text
 811 |             $entryId = null;
 812 |             if ($invoice->direction === 'receivable') {
 813 |                 if ($payment->method !== 'transfer') {
 814 |                     $this->invalid('Hanya transfer yang menunggu verifikasi bank.');
 815 |                 }
 816 |                 $entryId = Ledger::post('v2_payment', $paymentId, 'Verifikasi transfer '.$invoice->number, [
 817 |                     ['account_code' => self::BANK, 'debit' => $this->str($amount), 'credit' => '0.00'],
 818 |                     ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($amount)],
 819 |                 ]);
 820 |             } elseif ($invoice->direction === 'payable') {
 821 |                 $this->postPayable($actor, $invoice, $payment);
 822 | 
 823 |                 return;
 824 |             } else {
 825 |                 $this->invalid('Arah tagihan tidak valid.');
```

Payment invoice dan sale diperbarui — `app/Services/V2/Operations.php:828-842`

```text
 828 |             DB::table('v2_payments')->where('id', $paymentId)->update([
 829 |                 'status' => 'posted',
 830 |                 'approved_by' => $actor,
 831 |                 'journal_entry_id' => $entryId,
 832 |                 'updated_at' => now(),
 833 |             ]);
 834 |             DB::table('v2_invoices')->where('id', $invoice->id)->update([
 835 |                 'paid' => $this->str(Money::value((string) $invoice->paid, 2)->plus($amount)),
 836 |                 'status' => $amount->isEqualTo($remaining) ? 'paid' : 'open',
 837 |                 'updated_at' => now(),
 838 |             ]);
 839 | 
 840 |             if ($invoice->sale_id !== null) {
 841 |                 DB::table('v2_sales')->where('id', $invoice->sale_id)->update(['status' => $this->saleStatus(Money::value((string) $invoice->paid)->plus($amount), Money::value((string) $invoice->amount)), 'updated_at' => now()]);
 842 |             }
```

### RP17 — Setujui pengajuan supplier

Memanggil postPayable untuk payment_id/invoice_id yang sama: main memakai Kas Utama, transfer memakai Bank tanpa laci; cash memerlukan shift PEMOHON masih open/miliknya, uang laci cukup dan gerakan out supplier. Semua sumber memerlukan saldo akun cukup dan akun aktif. Payment posted, approved_by/journal_id terisi dan paid invoice/status diperbarui. Gagal guard mempertahankan pengajuan belum posted, bukan mengganti shift atau sumber otomatis.

Approve hanya status layak dan sisa cukup — `app/Services/V2/Operations.php:794-809`

```text
 794 |             $payment = DB::table('v2_payments')->where('id', $paymentId)->lockForUpdate()->first();
 795 | 
 796 |             if ($payment->status === 'posted') {
 797 |                 return;
 798 |             }
 799 |             if (! in_array($payment->status, ['pending', 'proposed'], true)) {
 800 |                 $this->invalid('Status pembayaran tidak dapat disetujui.');
 801 |             }
 802 |             $amount = Money::value((string) $payment->amount, 2);
 803 |             if (! $amount->isPositive()) {
 804 |                 $this->invalid('Nominal pembayaran tidak valid.');
 805 |             }
 806 |             $remaining = Money::value((string) $invoice->amount, 2)->minus(Money::value((string) $invoice->paid, 2));
 807 |             if ($amount->isGreaterThan($remaining)) {
 808 |                 $this->invalid('Nominal melebihi sisa tagihan.');
 809 |             }
```

Approve payable menggunakan postPayable — `app/Services/V2/Operations.php:820-825`

```text
 820 |             } elseif ($invoice->direction === 'payable') {
 821 |                 $this->postPayable($actor, $invoice, $payment);
 822 | 
 823 |                 return;
 824 |             } else {
 825 |                 $this->invalid('Arah tagihan tidak valid.');
```

Laci pemohon, saldo akun dan jurnal saat approve — `app/Services/V2/Operations.php:870-887`

```text
 870 |         if ($drawer) {
 871 |             $drawer = DB::table('v2_shifts')->where('id', $payment->shift_id)->lockForUpdate()->first();
 872 |             if (! $drawer || $drawer->status !== 'open' || (int) $drawer->user_id !== (int) $payment->requested_by) {
 873 |                 $this->invalid('Shift pengajuan tidak terbuka; ajukan ulang.');
 874 |             }
 875 |             $drawerBalance = Money::value((string) $drawer->opening_cash);
 876 |             foreach (DB::table('v2_shift_movements')->where('shift_id', $drawer->id)->get(['direction', 'amount']) as $movement) {
 877 |                 $drawerBalance = $movement->direction === 'in' ? $drawerBalance->plus((string) $movement->amount) : $drawerBalance->minus((string) $movement->amount);
 878 |             }
 879 |             if ($drawerBalance->isLessThan($amount)) {
 880 |                 $this->invalid('Uang tunai laci tidak cukup untuk pembayaran.');
 881 |             }
 882 |         }
 883 |         $this->guardAccount($account, $amount->negated(), $label);
 884 |         $entryId = Ledger::post('v2_payment', $paymentId, 'Pembayaran utang '.$invoice->number, [
 885 |             ['account_code' => self::AP, 'debit' => $this->str($amount), 'credit' => '0.00'],
 886 |             ['account_code' => $account, 'debit' => '0.00', 'credit' => $this->str($amount)],
 887 |         ]);
```

### RP18 — Tolak pembayaran menunggu

Payment menjadi rejected dengan resolution_note/resolved_by/resolved_at. Tidak mengubah paid invoice, sale, stok, laci, saldo kas/bank atau jurnal karena pembayaran belum diposting. Reject ulang dengan actor/catatan berbeda gagal. Tidak ada pembukaan kembali atau reversal untuk posted di endpoint ini.

Input alasan penolakan — `app/Http/Controllers/V2/WorkspaceController.php:353-360`

```text
 353 |     public function reject(Request $request, int $id): JsonResponse
 354 |     {
 355 |         $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
 356 | 
 357 |         return $this->run(function () use ($request, $id, $data): array {
 358 |             $this->operations->rejectPayment($request->user()->id, $id, $data['reason']);
 359 | 
 360 |             return ['status' => 'rejected'];
```

Akses dan replay rejected — `app/Services/V2/Operations.php:241-258`

```text
 241 |     public function rejectPayment(int $actor, int $paymentId, string $reason): void
 242 |     {
 243 |         $this->requireRole($actor, ['admin']);
 244 |         $note = trim($reason);
 245 |         $length = mb_strlen($note);
 246 |         if ($length < 5 || $length > 1000) {
 247 |             $this->invalid('Alasan penolakan 5 sampai 1000 karakter.');
 248 |         }
 249 | 
 250 |         DB::transaction(function () use ($actor, $paymentId, $note) {
 251 |             $payment = DB::table('v2_payments')->where('id', $paymentId)->lockForUpdate()->first();
 252 |             if (! $payment) {
 253 |                 $this->invalid('Pembayaran tidak ditemukan.');
 254 |             }
 255 |             if ($payment->status === 'rejected') {
 256 |                 if ((int) $payment->resolved_by === $actor && $payment->resolution_note === $note) {
 257 |                     return;
 258 |                 }
```

Guard status dan audit penolakan — `app/Services/V2/Operations.php:259-271`

```text
 259 |                 $this->invalid('Pembayaran sudah ditolak dengan catatan berbeda.');
 260 |             }
 261 |             if (! in_array($payment->status, ['pending', 'proposed'], true)) {
 262 |                 $this->invalid('Hanya pembayaran pending atau proposed yang dapat ditolak.');
 263 |             }
 264 | 
 265 |             DB::table('v2_payments')->where('id', $paymentId)->update([
 266 |                 'status' => 'rejected',
 267 |                 'resolution_note' => $note,
 268 |                 'resolved_by' => $actor,
 269 |                 'resolved_at' => now(),
 270 |                 'updated_at' => now(),
 271 |             ]);
```

## 01-kasir-shift — Kasir V2 — Penjualan dan lifecycle shift sampai rekap penutupan (berbasis kode)

### KS01 — Masuk Kasir V2 dan pembatasan peran

Operations::role mensyaratkan users.is_active dan v2_user_roles admin/cashier; selain itu 403. Workspace menampilkan shift/penjualan kasir sendiri, admin lintas kasir. Cutover membuat rute legacy selain root hanya arsip GET admin; bukan jalur checkout V2.

Cutover halaman POS — `app/Http/Controllers/PosController.php:15-19`

```text
  15 |     public function index(Request $request)
  16 |     {
  17 |         if (config('v2.enabled')) {
  18 |             return app(WorkspaceController::class)->index($request);
  19 |         }
```

Peran harus aktif dan berwenang — `app/Services/V2/Operations.php:34-48`

```text
  34 |     public function role(int $actor): string
  35 |     {
  36 |         $role = DB::table('v2_user_roles')->join('users', 'users.id', '=', 'v2_user_roles.user_id')->where('v2_user_roles.user_id', $actor)->where('users.is_active', true)->value('v2_user_roles.role');
  37 |         if (! is_string($role) || ! in_array($role, ['admin', 'cashier'], true)) {
  38 |             abort(403);
  39 |         }
  40 | 
  41 |         return $role;
  42 |     }
  43 | 
  44 |     public function requireRole(int $actor, array $roles): void
  45 |     {
  46 |         if (! in_array($this->role($actor), $roles, true)) {
  47 |             abort(403);
  48 |         }
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

### KS02 — Buka shift dengan modal admin, carry, atau gabungan

Transaksi mengunci shift asal bila carry; asal wajib closed, milik aktor, counted_cash tersedia/tidak negatif dan belum pernah dibawa. carry paling banyak uang hitung asal; both wajib modal melebihi seluruh uang hitung asal serta kedua komponen positif. Insert v2_shifts open, open_user_id=aktor, opening_cash/carry_amount/admin_amount dan carried_from_id. Tidak ada jurnal modal atau mutasi laci pada openShift; sumber admin adalah label sumber, bukan persetujuan admin.

Sumber dan nominal modal — `app/Services/V2/Operations.php:51-65`

```text
  51 |     public function openShift(int $actor, string $cash, string $source, ?int $carryFrom = null): int
  52 |     {
  53 |         $this->requireRole($actor, ['admin', 'cashier']);
  54 |         if (! in_array($source, ['admin', 'carry', 'both'], true)) {
  55 |             $this->invalid('Sumber modal tidak valid.');
  56 |         }
  57 |         if (in_array($source, ['carry', 'both'], true) && $carryFrom === null) {
  58 |             $this->invalid('Shift asal wajib untuk sumber carry atau both.');
  59 |         }
  60 |         if ($source === 'admin' && $carryFrom !== null) {
  61 |             $this->invalid('Sumber admin tidak boleh membawa shift asal.');
  62 |         }
  63 |         $opening = $this->amount($cash, 2, 'Modal awal');
  64 |         if ($opening->isNegative()) {
  65 |             $this->invalid('Modal awal tidak boleh negatif.');
```

Batas carry dan satu kali penggunaan — `app/Services/V2/Operations.php:92-109`

```text
  92 |                     if ($source === 'carry' && $opening->isGreaterThan($counted)) {
  93 |                         $this->invalid('Modal carry melebihi uang hitung shift asal.');
  94 |                     }
  95 |                     if ($source === 'both') {
  96 |                         if ($opening->isLessThanOrEqualTo($counted)) {
  97 |                             $this->invalid('Sumber both memerlukan modal melebihi uang hitung shift asal.');
  98 |                         }
  99 |                         $carry = $counted;
 100 |                         $admin = $opening->minus($counted);
 101 |                         if (! $carry->isPositive() || ! $admin->isPositive()) {
 102 |                             $this->invalid('Nilai carry dan modal admin harus lebih dari nol.');
 103 |                         }
 104 |                     } elseif ($source === 'carry') {
 105 |                         $carry = $opening;
 106 |                         $admin = Money::value('0');
 107 |                     }
 108 |                     if (DB::table('v2_shifts')->where('carried_from_id', $carryFrom)->exists()) {
 109 |                         $this->invalid('Saldo shift asal sudah dibawa.');
```

Simpan shift terbuka — `app/Services/V2/Operations.php:113-125`

```text
 113 |                 return DB::table('v2_shifts')->insertGetId([
 114 |                     'user_id' => $actor,
 115 |                     'open_user_id' => $actor,
 116 |                     'source' => $source,
 117 |                     'opening_cash' => $this->str($opening),
 118 |                     'carry_amount' => $this->str($carry),
 119 |                     'admin_amount' => $this->str($admin),
 120 |                     'status' => 'open',
 121 |                     'opened_at' => now(),
 122 |                     'carried_from_id' => $carryFrom,
 123 |                     'created_at' => now(),
 124 |                     'updated_at' => now(),
 125 |                 ]);
```

### KS03 — Susun keranjang, pembayaran, lalu tinjau sebelum konfirmasi

Dialog pembayaran lalu tinjau tetap belum menyimpan. Konfirmasi final menetapkan __finalConfirmed dan requestSubmit; handler membentuk payload dan kunci idempotensi. Respons sukses menampilkan nomor/status/kembalian, tautan nota, mengganti kunci dan mengosongkan item; event v2:sale-saved mengubah dialog menjadi hasil. Validasi UI bukan pengganti server.

Target form checkout V2 — `resources/views/v2/pos-cashier.blade.php:17-30`

```text
  17 |         <form id="form-checkout" action="{{ route('v2.sales.store') }}" novalidate>
  18 |             <!-- Informasi Pembeli / Pelanggan -->
  19 |             <div class="pos-buyer-card">
  20 |                 <div class="pos-buyer-header">
  21 |                     <label for="pos-customer-quick" class="pos-buyer-title">
  22 |                         <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
  23 |                         <span>Nama Pembeli</span>
  24 |                         <span id="pos-buyer-req" class="pos-req-indicator" hidden>(wajib)</span>
  25 |                     </label>
  26 |                     <span id="pos-buyer-badge" class="pos-buyer-badge badge-general">Pelanggan Umum</span>
  27 |                 </div>
  28 |                 <div class="pos-buyer-select-wrapper">
  29 |                     <select id="pos-customer-quick" class="pos-customer-select" aria-label="Pilih pelanggan transaksi">
  30 |                         <option value="">Pelanggan Umum (Walk-in / Tunai)</option>
```

Tinjau, konfirmasi, dan hasil — `resources/views/v2/pos-cashier-script.blade.php:155-167`

```text
 155 | function openPay(){if(!rows().length||!valid||saved)return;payment.showModal();refresh();showPaid();paidDisplay.focus({preventScroll:true});paidDisplay.select();}
 156 | function closePay(){if(submitting||form.dataset.busy)return;payment.close();payButton.focus({preventScroll:true});}
 157 | function cancelReview(){if(submitting||form.dataset.busy)return;review.close();payment.showModal();document.querySelector('#review-payment').focus({preventScroll:true});}
 158 | function trap(dialog,onEscape){dialog.addEventListener('cancel',e=>{e.preventDefault();onEscape();});dialog.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();onEscape();return;}if(e.key!=='Tab')return;const nodes=Array.from(dialog.querySelectorAll('button,input,select,textarea,summary,a[href]')).filter(el=>!el.disabled&&el.tabIndex>=0&&el.getBoundingClientRect().height);if(e.shiftKey&&document.activeElement===nodes[0]){e.preventDefault();nodes.at(-1).focus();}else if(!e.shiftKey&&document.activeElement===nodes.at(-1)){e.preventDefault();nodes[0].focus();}});}
 159 | trap(payment,closePay);trap(review,cancelReview);payButton.addEventListener('click',openPay);for(const b of document.querySelectorAll('[data-close-payment]'))b.addEventListener('click',closePay);for(const b of document.querySelectorAll('[data-cancel-review]'))b.addEventListener('click',cancelReview);
 160 | for(const el of form.querySelectorAll('input,select'))if(!el.closest('#items')){el.addEventListener('input',refresh);el.addEventListener('change',refresh);}
 161 | function fail(message){const box=form.querySelector('[data-error]');box.textContent=message;box.hidden=false;box.focus();}
 162 | function renderReview(){const box=document.querySelector('#checkout-review');box.replaceChildren();const list=document.createElement('ul');list.className='plain';for(const row of rows()){const li=document.createElement('li');li.textContent=row.querySelector('.cart-product-title').textContent+' × '+row.querySelector('[data-name=quantity]').value+' · '+row.querySelector('.item-subtotal').textContent;list.append(li);}box.append(list);const cid=form.elements.customer_id.value;const custText=cid?form.elements.customer_id.selectedOptions[0].textContent.trim():'Pelanggan Umum (Walk-in)';const lines=[['Pembeli',custText],...(contractorOn()?[['Harga','Harga kontraktor']]:[]),['Total',D.money(total)],['Metode',form.elements.payment_method.selectedOptions[0].textContent],['Dibayar sekarang',D.money(D.scaled(paid.value||'0',2))],['Kembalian',document.querySelector('#display-change').textContent]];if(form.elements.fulfillment.value==='delivery'){lines.splice(2,0,['Pengiriman','Dikirim ke '+(form.elements.destination.value.trim()||'-')+' · Ongkir '+D.money(D.scaled(document.querySelector('#delivery-fee').value||'0',2))]);}for(const [label,value]of lines){const p=document.createElement('p');p.className='review-line';const title=document.createElement('strong'),v=document.createElement('span');title.textContent=label;v.textContent=value;p.append(title,v);box.append(p);}}
 163 | document.querySelector('#review-payment').addEventListener('click',()=>{refresh();if(!valid)return;try{const tender=D.scaled(paid.value||'0',2),method=form.elements.payment_method.value;if(method==='cash'&&tender<total)throw Error('Nominal pembayaran kurang dari total.');if(method==='credit'&&tender>total)throw Error('Uang muka tidak boleh melebihi total.');if(method==='credit'&&!form.elements.customer_id.value)throw Error('Pilih pelanggan untuk transaksi kredit.');if(form.elements.fulfillment.value==='delivery'&&!form.elements.destination.value.trim())throw Error('Tujuan kirim wajib diisi.');renderReview();payment.close();review.showModal();confirm.focus({preventScroll:true});}catch(e){fail(e.message);}});
 164 | let sold=[];const payTitle=payment.querySelector('.popup-heading h2').textContent;
 165 | confirm.addEventListener('click',()=>{if(submitting||form.dataset.busy||saved)return;sold=rows().map(row=>({id:row.querySelector('[data-name=product_id]').value,qty:row.querySelector('[data-name=quantity]').value}));submitting=true;confirm.disabled=true;review.close();payment.showModal();for(const input of form.querySelectorAll('[inputmode=decimal]'))input.value=normalize(input.value);form.__finalConfirmed=true;form.requestSubmit(document.querySelector('#checkout-submit'));});
 166 | const busy=new MutationObserver(()=>{if(!form.dataset.busy&&submitting&&!saved){submitting=false;confirm.disabled=false;}});busy.observe(form,{attributes:true,attributeFilter:['data-busy']});
 167 | form.addEventListener('v2:sale-saved',e=>{saved=true;submitting=false;try{localStorage.removeItem(cartKey);}catch{}payment.querySelector('.popup-heading h2').textContent='Transaksi Tersimpan — Periksa Sebelum Cetak';document.querySelector('#review-payment').hidden=true;payButton.disabled=true;document.querySelector('#cart-empty-row').hidden=false;document.querySelector('#cart-item-count').textContent='0 jenis item';const link=document.querySelector('#checkout-result a');if(link)link.focus({preventScroll:true});const next=document.createElement('button');next.type='button';next.textContent='Penjualan berikutnya';next.addEventListener('click',()=>{document.querySelector('dialog.ur-dialog[open]')?.close();window.kasirNextSale();});document.querySelector('#checkout-result').append(document.createElement('br'),next);});
```

Payload penjualan — `resources/views/v2/workspace.blade.php:581-598`

```text
 581 |             var method = val(checkout, 'payment_method');
 582 |             var payload = {
 583 |                 items: items,
 584 |                 delivery_fee: val(checkout, 'delivery_fee'),
 585 |                 payment_method: method,
 586 |                 deposit_method: method === 'credit' ? val(checkout, 'deposit_method') : null,
 587 |                 paid_amount: val(checkout, 'paid_amount'),
 588 |                 idempotency_key: val(checkout, 'idempotency_key'),
 589 |                 fulfillment: val(checkout, 'fulfillment'),
 590 |                 price_tier: val(checkout, 'price_tier') || 'retail'
 591 |             };
 592 |             var cid = val(checkout, 'customer_id');
 593 |             payload.customer_id = cid === '' ? null : cid;
 594 |             payload.destination = payload.fulfillment === 'delivery' ? val(checkout, 'destination') : null;
 595 |             var dt = document.getElementById('inv-disc-type').value;
 596 |             var dv = val(checkout, 'discount_value');
 597 |             if (dv !== '') { payload.discount = { type: dt, value: dv }; }
 598 | 
```

### KS04 — Validasi server, kunci shift, dan cegah checkout ganda

Di dalam satu transaksi, openShiftFor mengunci shift open milik aktor SEBELUM mencari replay. Kunci lama hanya dapat diulang aktor sama dengan request_hash sama; berbeda aktor 403 atau isi berbeda ditolak. Replay mengembalikan transaksi lama tanpa menulis lagi; sesudah shift ditutup replay tetap dapat gagal karena syarat shift open diperiksa lebih dulu. Produk duplikat/tidak ditemukan/arsip, jumlah tidak positif dan stok kurang ditolak.

Validasi kontrak endpoint checkout — `app/Http/Controllers/V2/WorkspaceController.php:297-314`

```text
 297 |         $data = $request->validate([
 298 |             'items' => ['required', 'array', 'min:1', 'max:100'],
 299 |             'items.*.product_id' => ['required', 'integer', 'min:1'],
 300 |             'items.*.quantity' => ['required', 'string', 'max:30'],
 301 |             'items.*.discount' => ['sometimes', 'array:type,value'],
 302 |             'items.*.discount.type' => ['required_with:items.*.discount', Rule::in(['amount', 'percent'])],
 303 |             'items.*.discount.value' => ['required_with:items.*.discount', 'string', 'max:30'],
 304 |             'discount' => ['sometimes', 'array:type,value'],
 305 |             'discount.type' => ['required_with:discount', Rule::in(['amount', 'percent'])],
 306 |             'discount.value' => ['required_with:discount', 'string', 'max:30'],
 307 |             'delivery_fee' => ['required', 'string', 'max:30'],
 308 |             'customer_id' => ['nullable', 'integer', 'min:1'],
 309 |             'payment_method' => ['required', Rule::in(['cash', 'transfer', 'credit'])],
 310 |             'deposit_method' => ['nullable', Rule::in(['cash', 'transfer'])],
 311 |             'paid_amount' => ['required', 'string', 'max:30'],
 312 |             'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
 313 |             'fulfillment' => ['required', Rule::in(['pickup', 'delivery'])],
 314 |             'destination' => ['nullable', 'required_if:fulfillment,delivery', 'string', 'max:255'],
```

Kunci shift sebelum pemeriksaan replay — `app/Services/V2/Operations.php:323-338`

```text
 323 |         return DB::transaction(function () use ($actor, $data, $key, $method, $fulfillment, $destination, $items, $deliveryFee, $customerId, $vehicleId, $driverName, $tier) {
 324 |             $shift = $this->openShiftFor($actor, true);
 325 |             $requestHash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
 326 |             $existing = DB::table('v2_sales')->where('idempotency_key', $key)->first();
 327 |             if ($existing) {
 328 |                 abort_unless((int) $existing->user_id === $actor, 403);
 329 |                 if ($existing->request_hash !== $requestHash) {
 330 |                     $this->invalid('Kunci transaksi sudah dipakai untuk isi berbeda.');
 331 |                 }
 332 | 
 333 |                 return $this->replay((int) $existing->id);
 334 |             }
 335 | 
 336 |             if ($vehicleId !== null && ! DB::table('vehicles')->where('id', $vehicleId)->exists()) {
 337 |                 $this->invalid('Kendaraan tidak ditemukan.');
 338 |             }
```

Shift open wajib milik aktor — `app/Services/V2/Operations.php:962-973`

```text
 962 |     private function openShiftFor(int $actor, bool $lock): object
 963 |     {
 964 |         $query = DB::table('v2_shifts')->where('user_id', $actor)->where('status', 'open');
 965 |         if ($lock) {
 966 |             $query->lockForUpdate();
 967 |         }
 968 |         $shift = $query->first();
 969 |         if (! $shift) {
 970 |             $this->invalid('Shift kasir belum dibuka.');
 971 |         }
 972 | 
 973 |         return $shift;
```

### KS05 — Posting nota, stok, HPP, piutang dan pendapatan secara atomik

Dalam transaksi checkout yang sama dibuat INV v2_sales dan snapshot v2_sale_items, stok products berkurang dan stock_movements reference_type=v2_sale negatif. Semua metode membuat AR v2_invoices dengan amount total dan paid sesuai cabang pembayaran; invoice paid jika lunas, selain itu open. Ledger v2_sale: DR1103 total; CR4101 barang net bila positif; CR4102 ongkir bila positif; DR5101/CR1301 HPP bila positif. Akun aktif dan jurnal seimbang wajib; kegagalan membatalkan keseluruhan checkout. Pilihan cash/transfer/credit berikut adalah alternatif dalam transaksi ini, bukan transaksi serial.

Kurangi stok dan catat mutasi penjualan — `app/Services/V2/Operations.php:502-516`

```text
 502 |                 $balance = $row['stock']->minus($row['quantity'])->toScale(4, RoundingMode::HalfUp);
 503 |                 DB::table('products')->where('id', $row['product_id'])->update([
 504 |                     'stock_quantity' => $this->str($balance, 4),
 505 |                     'updated_at' => now(),
 506 |                 ]);
 507 |                 DB::table('stock_movements')->insert([
 508 |                     'product_id' => $row['product_id'],
 509 |                     'reference_type' => 'v2_sale',
 510 |                     'reference_id' => $saleId,
 511 |                     'quantity_delta' => $this->str($row['quantity']->negated(), 4),
 512 |                     'balance_after' => $this->str($balance, 4),
 513 |                     'notes' => 'Penjualan '.$number,
 514 |                     'created_at' => now(),
 515 |                     'updated_at' => now(),
 516 |                 ]);
```

Buat AR untuk semua metode — `app/Services/V2/Operations.php:519-532`

```text
 519 |             $invoiceId = DB::table('v2_invoices')->insertGetId([
 520 |                 'direction' => 'receivable',
 521 |                 'party_id' => $customerId,
 522 |                 'sale_id' => $saleId,
 523 |                 'number' => 'TMP-'.bin2hex(random_bytes(8)),
 524 |                 'amount' => $this->str($total),
 525 |                 'paid' => $this->str($paid),
 526 |                 'due_date' => null,
 527 |                 'status' => $paid->isGreaterThanOrEqualTo($total) ? 'paid' : 'open',
 528 |                 'created_at' => now(),
 529 |                 'updated_at' => now(),
 530 |             ]);
 531 |             $invoiceNumber = 'AR-'.now()->format('Ymd').'-'.str_pad((string) $invoiceId, 5, '0', STR_PAD_LEFT);
 532 |             DB::table('v2_invoices')->where('id', $invoiceId)->update(['number' => $invoiceNumber, 'updated_at' => now()]);
```

Jurnal pendapatan dan HPP — `app/Services/V2/Operations.php:534-548`

```text
 534 |             $goodsNet = $goodsBase->minus($invoiceDiscount)->toScale(2, RoundingMode::HalfUp);
 535 |             $saleLines = [
 536 |                 ['account_code' => self::AR, 'debit' => $this->str($total), 'credit' => '0.00'],
 537 |             ];
 538 |             if ($goodsNet->isPositive()) {
 539 |                 $saleLines[] = ['account_code' => self::GOODS_REVENUE, 'debit' => '0.00', 'credit' => $this->str($goodsNet)];
 540 |             }
 541 |             if ($deliveryFee->isPositive()) {
 542 |                 $saleLines[] = ['account_code' => self::DELIVERY_REVENUE, 'debit' => '0.00', 'credit' => $this->str($deliveryFee)];
 543 |             }
 544 |             if ($cogs->isPositive()) {
 545 |                 $saleLines[] = ['account_code' => self::COGS, 'debit' => $this->str($cogs), 'credit' => '0.00'];
 546 |                 $saleLines[] = ['account_code' => self::INVENTORY, 'debit' => '0.00', 'credit' => $this->str($cogs)];
 547 |             }
 548 |             Ledger::post('v2_sale', $saleId, 'Penjualan '.$number, $saleLines);
```

### KS06 — Cabang tunai: lunas, kembalian, dan masuk laci

paid=total, change=tendered−total, status sale paid dan invoice paid. Buat pembayaran cash posted/approved_by aktor; jurnal DR1101/CR1103 sebesar total, bukan uang diserahkan. Mutasi laci in kind sale reference v2_sale sebesar paid neto, sehingga kembalian tidak dihitung sebagai penerimaan tambahan. Nota/stok/HPP/pendapatan tetap melalui posting bersama KS05.

Uang tunai cukup dan kembalian — `app/Services/V2/Operations.php:424-433`

```text
 424 |             if ($method === 'cash') {
 425 |                 if (! array_key_exists('paid_amount', $data)) {
 426 |                     $this->invalid('Uang tunai diterima wajib diisi.');
 427 |                 }
 428 |                 $tendered = $this->amount($data['paid_amount'], 2, 'Uang diterima');
 429 |                 if ($tendered->isLessThan($total)) {
 430 |                     $this->invalid('Uang tunai kurang dari total.');
 431 |                 }
 432 |                 $paid = $total;
 433 |                 $change = $tendered->minus($total);
```

Pembayaran tunai langsung posted — `app/Services/V2/Operations.php:550-567`

```text
 550 |             if ($paid->isPositive()) {
 551 |                 $paymentId = DB::table('v2_payments')->insertGetId([
 552 |                     'invoice_id' => $invoiceId,
 553 |                     'shift_id' => (int) $shift->id,
 554 |                     'amount' => $this->str($paid),
 555 |                     'method' => 'cash',
 556 |                     'status' => 'posted',
 557 |                     'requested_by' => $actor,
 558 |                     'approved_by' => $actor,
 559 |                     'reference' => null,
 560 |                     'idempotency_key' => $key.':payment',
 561 |                     'request_hash' => $requestHash,
 562 |                     'journal_entry_id' => null,
 563 |                     'created_at' => now(),
 564 |                     'updated_at' => now(),
 565 |                 ]);
 566 |                 $entryId = Ledger::post('v2_payment', $paymentId, 'Penerimaan tunai '.$number, [
 567 |                     ['account_code' => self::CASH, 'debit' => $this->str($paid), 'credit' => '0.00'],
```

Jurnal kas/piutang dan mutasi laci neto — `app/Services/V2/Operations.php:566-582`

```text
 566 |                 $entryId = Ledger::post('v2_payment', $paymentId, 'Penerimaan tunai '.$number, [
 567 |                     ['account_code' => self::CASH, 'debit' => $this->str($paid), 'credit' => '0.00'],
 568 |                     ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($paid)],
 569 |                 ]);
 570 |                 DB::table('v2_payments')->where('id', $paymentId)->update(['journal_entry_id' => $entryId, 'updated_at' => now()]);
 571 | 
 572 |                 DB::table('v2_shift_movements')->insert([
 573 |                     'shift_id' => (int) $shift->id,
 574 |                     'direction' => 'in',
 575 |                     'kind' => $method === 'cash' ? 'sale' : 'collection',
 576 |                     'amount' => $this->str($paid),
 577 |                     'reference_type' => $method === 'cash' ? 'v2_sale' : 'v2_payment',
 578 |                     'reference_id' => $method === 'cash' ? $saleId : $paymentId,
 579 |                     'note' => 'Penjualan '.$number,
 580 |                     'created_at' => now(),
 581 |                     'updated_at' => now(),
 582 |                 ]);
```

### KS07 — Cabang transfer: tagihan terbuka dan verifikasi tertunda

paid tetap nol sehingga sale unpaid dan AR open. Jika tendered positif dibuat payment transfer pending, shift_id null, approved_by/journal_entry_id null; belum menambah bank, belum mengurangi AR dan tidak ada mutasi laci. Jika nominal nol, tidak dibuat payment sama sekali. Stok/HPP/pendapatan tetap diposting saat checkout, tidak menunggu verifikasi transfer; lanjut KS11 hanya jika payment pending benar-benar ada.

Transfer nol atau total, belum diakui paid — `app/Services/V2/Operations.php:435-443`

```text
 435 |                 $tendered = $this->amount($data['paid_amount'] ?? 0, 2, 'Nominal dibayar');
 436 |                 if ($tendered->isNegative()) {
 437 |                     $this->invalid('Nominal dibayar tidak boleh negatif.');
 438 |                 }
 439 |                 if ($method === 'transfer') {
 440 |                     if (! $tendered->isZero() && ! $tendered->isEqualTo($total)) {
 441 |                         $this->invalid('Transfer harus nol atau tepat sebesar total.');
 442 |                     }
 443 |                     $paid = Money::value('0');
```

Transfer pending tanpa shift/jurnal — `app/Services/V2/Operations.php:583-598`

```text
 583 |             } elseif ($tendered->isPositive() && ($method === 'transfer' || $depositMethod === 'transfer')) {
 584 |                 DB::table('v2_payments')->insert([
 585 |                     'invoice_id' => $invoiceId,
 586 |                     'shift_id' => null,
 587 |                     'amount' => $this->str($tendered),
 588 |                     'method' => 'transfer',
 589 |                     'status' => 'pending',
 590 |                     'requested_by' => $actor,
 591 |                     'approved_by' => null,
 592 |                     'reference' => null,
 593 |                     'idempotency_key' => $key.':payment',
 594 |                     'request_hash' => $requestHash,
 595 |                     'journal_entry_id' => null,
 596 |                     'created_at' => now(),
 597 |                     'updated_at' => now(),
 598 |                 ]);
```

Status sale unpaid/partial/paid — `app/Services/V2/Operations.php:996-1002`

```text
 996 |     private function saleStatus(BigDecimal $paid, BigDecimal $total): string
 997 |     {
 998 |         if ($paid->isZero()) {
 999 |             return 'unpaid';
1000 |         }
1001 | 
1002 |         return $paid->isGreaterThanOrEqualTo($total) ? 'paid' : 'partial';
```

### KS08 — Cabang kredit: tanpa DP, DP tunai, atau DP transfer

Tanpa DP: paid nol, sale unpaid, AR open, tanpa payment. DP cash: paid langsung sebesar DP, payment cash posted DR1101/CR1103 dan mutasi laci in kind collection reference v2_payment; sale partial atau paid bila DP=total, AR open/paid mengikuti. DP transfer: paid tetap nol; buat transfer pending hanya sebesar DP, tanpa bank/laci/penurunan AR sebelum verifikasi KS11. Label metode sale tetap credit; rekap menggolongkan total nota kredit, bukan memecah DP menjadi omzet tunai.

Validasi DP dan nilai paid kredit — `app/Services/V2/Operations.php:444-460`

```text
 444 |                 } else {
 445 |                     if ($tendered->isGreaterThan($total)) {
 446 |                         $this->invalid('Uang muka melebihi total.');
 447 |                     }
 448 |                     if ($tendered->isPositive()) {
 449 |                         $depositMethod = (string) ($data['deposit_method'] ?? '');
 450 |                         if (! in_array($depositMethod, ['cash', 'transfer'], true)) {
 451 |                             $this->invalid('Metode uang muka wajib cash atau transfer.');
 452 |                         }
 453 |                         $paid = $depositMethod === 'cash' ? $tendered : Money::value('0');
 454 |                     } else {
 455 |                         $paid = Money::value('0');
 456 |                     }
 457 |                 }
 458 |             }
 459 | 
 460 |             $status = $this->saleStatus($paid, $total);
```

DP tunai sebagai collection laci — `app/Services/V2/Operations.php:566-582`

```text
 566 |                 $entryId = Ledger::post('v2_payment', $paymentId, 'Penerimaan tunai '.$number, [
 567 |                     ['account_code' => self::CASH, 'debit' => $this->str($paid), 'credit' => '0.00'],
 568 |                     ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($paid)],
 569 |                 ]);
 570 |                 DB::table('v2_payments')->where('id', $paymentId)->update(['journal_entry_id' => $entryId, 'updated_at' => now()]);
 571 | 
 572 |                 DB::table('v2_shift_movements')->insert([
 573 |                     'shift_id' => (int) $shift->id,
 574 |                     'direction' => 'in',
 575 |                     'kind' => $method === 'cash' ? 'sale' : 'collection',
 576 |                     'amount' => $this->str($paid),
 577 |                     'reference_type' => $method === 'cash' ? 'v2_sale' : 'v2_payment',
 578 |                     'reference_id' => $method === 'cash' ? $saleId : $paymentId,
 579 |                     'note' => 'Penjualan '.$number,
 580 |                     'created_at' => now(),
 581 |                     'updated_at' => now(),
 582 |                 ]);
```

DP transfer tetap pending — `app/Services/V2/Operations.php:583-598`

```text
 583 |             } elseif ($tendered->isPositive() && ($method === 'transfer' || $depositMethod === 'transfer')) {
 584 |                 DB::table('v2_payments')->insert([
 585 |                     'invoice_id' => $invoiceId,
 586 |                     'shift_id' => null,
 587 |                     'amount' => $this->str($tendered),
 588 |                     'method' => 'transfer',
 589 |                     'status' => 'pending',
 590 |                     'requested_by' => $actor,
 591 |                     'approved_by' => null,
 592 |                     'reference' => null,
 593 |                     'idempotency_key' => $key.':payment',
 594 |                     'request_hash' => $requestHash,
 595 |                     'journal_entry_id' => null,
 596 |                     'created_at' => now(),
 597 |                     'updated_at' => now(),
 598 |                 ]);
```

### KS09 — Pilihan kirim membuat surat jalan prepared, bukan otomatis dispatch

Buat v2_deliveries terkait sale_id, nomor SJ, destination, status prepared; pickup tidak membuat delivery. Checkout mengembalikan sale_id, invoice_id, total, paid, change, status dan delivery_number. Surat jalan diserahkan ke alur downstream POST /v2/deliveries/{id}/dispatch, tidak dibuktikan sebagai prasyarat tutup shift atau otomatis terkirim/lunas. Controller checkout tidak memasukkan vehicle_id/driver_name ke data tervalidasi meski service memiliki opsi tersebut.

Surat jalan hanya disiapkan untuk delivery — `app/Services/V2/Operations.php:601-615`

```text
 601 |             $deliveryNumber = null;
 602 |             if ($fulfillment === 'delivery') {
 603 |                 $deliveryId = DB::table('v2_deliveries')->insertGetId([
 604 |                     'sale_id' => $saleId,
 605 |                     'number' => 'TMP-'.bin2hex(random_bytes(8)),
 606 |                     'vehicle_id' => $vehicleId,
 607 |                     'driver_name' => $driverName,
 608 |                     'destination' => $destination,
 609 |                     'status' => 'prepared',
 610 |                     'created_at' => now(),
 611 |                     'updated_at' => now(),
 612 |                 ]);
 613 |                 $deliveryNumber = 'SJ-'.now()->format('Ymd').'-'.str_pad((string) $deliveryId, 5, '0', STR_PAD_LEFT);
 614 |                 DB::table('v2_deliveries')->where('id', $deliveryId)->update(['number' => $deliveryNumber, 'updated_at' => now()]);
 615 |             }
```

Hasil transaksi — `app/Services/V2/Operations.php:617-627`

```text
 617 |             return [
 618 |                 'sale_id' => $saleId,
 619 |                 'number' => $number,
 620 |                 'invoice_id' => $invoiceId,
 621 |                 'invoice_number' => $invoiceNumber,
 622 |                 'total' => $this->str($total),
 623 |                 'paid' => $this->str($paid),
 624 |                 'change' => $this->str($change),
 625 |                 'status' => $status,
 626 |                 'delivery_number' => $deliveryNumber,
 627 |             ];
```

Pointer endpoint dokumen/dispatch downstream — `routes/v2.php:16-19`

```text
  16 |     Route::get('/documents/{id}/{kind}', [WorkspaceController::class, 'document'])->whereNumber('id')->whereIn('kind', ['receipt', 'delivery'])->name('documents');
  17 |     Route::post('/opening-balances', [WorkspaceController::class, 'opening'])->name('openings.store');
  18 |     Route::post('/deliveries/{id}/dispatch', [WorkspaceController::class, 'dispatch'])->whereNumber('id')->name('deliveries.dispatch');
  19 |     Route::post('/deliveries/{id}/receive', [WorkspaceController::class, 'receiveDelivery'])->whereNumber('id')->name('deliveries.receive');
```

### KS10 — Lanjutan piutang: penerimaan cash atau pengajuan transfer

Transfer membuat payment pending shift_id null tanpa mengubah AR/sale/laci hingga KS11. Cash wajib shift open milik penerima saat ini: payment posted, DR1101/CR1103, mutasi in collection pada shift penerimaan, invoice.paid bertambah dan sale partial/paid. Shift penerimaan bisa berbeda dari shift checkout; jangan menghubungkan penerimaan ini ke rekap shift penjualan lama tanpa kecocokan shift_id mutasi.

Pagar AR, entri dobel dan sisa tagihan — `app/Services/V2/Operations.php:668-680`

```text
 668 |             if ($invoice->direction === 'receivable' && $method === 'main') {
 669 |                 $this->invalid('Kas Utama hanya untuk pembayaran ke supplier.');
 670 |             }
 671 |             if ($seenPaymentId !== null && ! $confirmed) {
 672 |                 $this->guardNewerPayments($invoiceId, $seenPaymentId);
 673 |             }
 674 | 
 675 |             $remaining = Money::value((string) $invoice->amount, 2)->minus(Money::value((string) $invoice->paid, 2));
 676 |             if (! $remaining->isPositive()) {
 677 |                 $this->invalid('Tagihan sudah lunas.');
 678 |             }
 679 |             if ($value->isGreaterThan($remaining)) {
 680 |                 $this->invalid('Nominal melebihi sisa tagihan.');
```

Transfer lanjutan tetap pending — `app/Services/V2/Operations.php:713-730`

```text
 713 |             if ($method === 'transfer') {
 714 |                 return DB::table('v2_payments')->insertGetId([
 715 |                     'invoice_id' => $invoiceId,
 716 |                     'shift_id' => null,
 717 |                     'amount' => $this->str($value),
 718 |                     'method' => 'transfer',
 719 |                     'status' => 'pending',
 720 |                     'requested_by' => $actor,
 721 |                     'approved_by' => null,
 722 |                     'reference' => $reference,
 723 |                     'idempotency_key' => $key,
 724 |                     'request_hash' => $requestHash,
 725 |                     'journal_entry_id' => null,
 726 |                     'created_at' => now(),
 727 |                     'updated_at' => now(),
 728 |                 ]);
 729 |             }
 730 | 
```

Jurnal dan mutasi collection tunai — `app/Services/V2/Operations.php:748-764`

```text
 748 |             $entryId = Ledger::post('v2_payment', $paymentId, 'Penerimaan '.$invoice->number, [
 749 |                 ['account_code' => self::CASH, 'debit' => $this->str($value), 'credit' => '0.00'],
 750 |                 ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($value)],
 751 |             ]);
 752 |             DB::table('v2_payments')->where('id', $paymentId)->update(['journal_entry_id' => $entryId, 'updated_at' => now()]);
 753 | 
 754 |             DB::table('v2_shift_movements')->insert([
 755 |                 'shift_id' => (int) $shift->id,
 756 |                 'direction' => 'in',
 757 |                 'kind' => 'collection',
 758 |                 'amount' => $this->str($value),
 759 |                 'reference_type' => 'v2_payment',
 760 |                 'reference_id' => $paymentId,
 761 |                 'note' => 'Penerimaan '.$invoice->number,
 762 |                 'created_at' => now(),
 763 |                 'updated_at' => now(),
 764 |             ]);
```

### KS11 — Verifikasi atau tolak transfer pada invoice penjualan

Approve mengunci invoice/payment, posted replay tanpa menulis, status lain selain pending/proposed ditolak; nominal positif dan tidak melebihi sisa. Untuk receivable hanya transfer: jurnal DR1102/CR1103, payment posted + approved_by/journal_entry_id, invoice.paid bertambah dan sale diperbarui partial/paid. Tidak ada mutasi laci. Reject hanya pending/proposed: payment rejected beserta alasan/resolved_by/at; invoice, sale, stok dan jurnal penjualan tidak dibalik. Pending transfer tidak harus selesai sebelum shift ditutup karena shift_id null.

Transfer terverifikasi membukukan bank dan AR — `app/Services/V2/Operations.php:811-819`

```text
 811 |             $entryId = null;
 812 |             if ($invoice->direction === 'receivable') {
 813 |                 if ($payment->method !== 'transfer') {
 814 |                     $this->invalid('Hanya transfer yang menunggu verifikasi bank.');
 815 |                 }
 816 |                 $entryId = Ledger::post('v2_payment', $paymentId, 'Verifikasi transfer '.$invoice->number, [
 817 |                     ['account_code' => self::BANK, 'debit' => $this->str($amount), 'credit' => '0.00'],
 818 |                     ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($amount)],
 819 |                 ]);
```

Posting payment dan pembaruan invoice/sale — `app/Services/V2/Operations.php:828-842`

```text
 828 |             DB::table('v2_payments')->where('id', $paymentId)->update([
 829 |                 'status' => 'posted',
 830 |                 'approved_by' => $actor,
 831 |                 'journal_entry_id' => $entryId,
 832 |                 'updated_at' => now(),
 833 |             ]);
 834 |             DB::table('v2_invoices')->where('id', $invoice->id)->update([
 835 |                 'paid' => $this->str(Money::value((string) $invoice->paid, 2)->plus($amount)),
 836 |                 'status' => $amount->isEqualTo($remaining) ? 'paid' : 'open',
 837 |                 'updated_at' => now(),
 838 |             ]);
 839 | 
 840 |             if ($invoice->sale_id !== null) {
 841 |                 DB::table('v2_sales')->where('id', $invoice->sale_id)->update(['status' => $this->saleStatus(Money::value((string) $invoice->paid)->plus($amount), Money::value((string) $invoice->amount)), 'updated_at' => now()]);
 842 |             }
```

Penolakan hanya mengubah payment — `app/Services/V2/Operations.php:261-271`

```text
 261 |             if (! in_array($payment->status, ['pending', 'proposed'], true)) {
 262 |                 $this->invalid('Hanya pembayaran pending atau proposed yang dapat ditolak.');
 263 |             }
 264 | 
 265 |             DB::table('v2_payments')->where('id', $paymentId)->update([
 266 |                 'status' => 'rejected',
 267 |                 'resolution_note' => $note,
 268 |                 'resolved_by' => $actor,
 269 |                 'resolved_at' => now(),
 270 |                 'updated_at' => now(),
 271 |             ]);
```

### KS12 — Ambil rekap penutupan dari shift yang masih open

Read-only: hitung jumlah/total nota v2_sales.shift_id dan kelompok payment_method cash/transfer/credit, terlepas lunas/menunggu. Hitung laci dari opening_cash + seluruh movement in − movement out; rincian sale/collection/other in dan supplier/other out. Hanya payment method cash status proposed shift_id yang sama dihitung sebagai pending_drawer_payments; transfer pending bukan pending laci dan jumlah pending bukan pengurang expected. Kembalikan scope shift serta fingerprint, tidak menulis jurnal atau saldo bank/Kas Utama.

Otorisasi rekap dan sumber penjualan shift — `app/Services/V2/ShiftClosingSummary.php:19-36`

```text
  19 |     public function build(int $actor, int $shiftId): array
  20 |     {
  21 |         $role = $this->operations->role($actor);
  22 |         abort_unless(in_array($role, ['admin', 'cashier'], true), 403);
  23 | 
  24 |         return DB::transaction(function () use ($actor, $role, $shiftId): array {
  25 |             $shift = DB::table('v2_shifts')->where('id', $shiftId)->lockForUpdate()->first();
  26 |             abort_unless($shift, 404);
  27 |             if ($role !== 'admin' && (int) $shift->user_id !== $actor) {
  28 |                 abort(403);
  29 |             }
  30 |             if ($shift->status !== 'open') {
  31 |                 throw ValidationException::withMessages(['shift' => 'Rekap hanya tersedia untuk shift yang masih terbuka.']);
  32 |             }
  33 | 
  34 |             $cashier = (string) DB::table('users')->where('id', $shift->user_id)->value('name');
  35 | 
  36 |             $sales = DB::table('v2_sales')->where('shift_id', $shiftId)->get(['id', 'payment_method', 'total']);
```

Kelompok total menurut metode, bukan uang diterima — `app/Services/V2/ShiftClosingSummary.php:42-54`

```text
  42 |             foreach ($sales as $sale) {
  43 |                 $salesCount++;
  44 |                 $total = Money::value((string) $sale->total, 2);
  45 |                 $salesTotal = $salesTotal->plus($total);
  46 |                 match ($sale->payment_method) {
  47 |                     'cash' => $salesCash = $salesCash->plus($total),
  48 |                     'transfer' => $salesTransfer = $salesTransfer->plus($total),
  49 |                     'credit' => $salesCredit = $salesCredit->plus($total),
  50 |                     default => null,
  51 |                 };
  52 |             }
  53 | 
  54 |             $movements = DB::table('v2_shift_movements')->where('shift_id', $shiftId)->orderBy('id')->get(['id', 'direction', 'kind', 'amount']);
```

Expected, pending cash dan fingerprint — `app/Services/V2/ShiftClosingSummary.php:83-99`

```text
  83 |             $opening = Money::value((string) $shift->opening_cash, 2);
  84 |             $expected = $opening->plus($cashIn)->minus($cashOut);
  85 | 
  86 |             $pending = DB::table('v2_payments')->where('shift_id', $shiftId)->where('method', 'cash')->where('status', 'proposed')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
  87 |             $fingerprint = $this->operations->closingFingerprint($shiftId);
  88 | 
  89 |             return [
  90 |                 'shift_id' => $shiftId,
  91 |                 'cashier' => $cashier,
  92 |                 'opened_at' => (string) $shift->opened_at,
  93 |                 'scope' => 'shift',
  94 |                 'sales' => [
  95 |                     'count' => $salesCount,
  96 |                     'total' => $this->str($salesTotal),
  97 |                     'cash' => $this->str($salesCash),
  98 |                     'transfer' => $this->str($salesTransfer),
  99 |                     'credit' => $this->str($salesCredit),
```

### KS13 — Bandingkan uang fisik dengan perkiraan laci

UI menampilkan total nota per metode dan kas awal/masuk/keluar/seharusnya; pending tunai diperlihatkan sebagai peringatan. BigInt menghitung sen: counted−expected; tampil Cocok, Selisih lebih atau kurang. Ini hanya perbandingan klien, keputusan akhir tetap closeShift. Saat form dikirim POST /v2/shifts/{id}/close membawa counted dan fingerprint atau null; UI tetap mengizinkan tutup ketika fetch rekap gagal.

Simpan fingerprint/expected dan rincian penjualan — `resources/views/v2/workspace.blade.php:713-725`

```text
 713 |     function renderRecap(form, data) {
 714 |         const box = form.querySelector('[data-close-recap]');
 715 |         box.replaceChildren();
 716 |         form._fingerprint = data.fingerprint;
 717 |         form._expected = data.cash.expected;
 718 | 
 719 |         const sales = document.createElement('dl');
 720 |         sales.className = 'recap-sales';
 721 |         sales.append(
 722 |             dlRow('Penjualan (' + data.sales.count + ' nota)', fmt(data.sales.total)),
 723 |             dlRow('— tunai', fmt(data.sales.cash)),
 724 |             dlRow('— transfer', fmt(data.sales.transfer)),
 725 |             dlRow('— kredit', fmt(data.sales.credit)),
```

Banding uang fisik menggunakan sen BigInt — `resources/views/v2/workspace.blade.php:745-755`

```text
 745 |         const counted = form.elements.counted;
 746 |         const match = form.querySelector('[data-close-match]');
 747 |         const recalc = () => {
 748 |             const raw = counted.value;
 749 |             if (!raw) { match.hidden = true; return; }
 750 |             match.hidden = false;
 751 |             const diff = cents(raw) - cents(form._expected);
 752 |             if (diff === 0n) { match.className = 'close-match ok'; match.textContent = 'Cocok dengan perkiraan ' + fmt(form._expected) + '.'; }
 753 |             else { match.className = 'close-match warn'; match.textContent = 'Selisih ' + (diff > 0n ? 'lebih ' : 'kurang ') + fmtCents(diff < 0n ? -diff : diff) + ' dari perkiraan.'; }
 754 |         };
 755 |         counted.addEventListener('input', recalc);
```

Payload penutupan shift — `resources/views/v2/workspace.blade.php:508-512`

```text
 508 |     document.addEventListener('submit', function (ev) {
 509 |         var form = ev.target.closest && ev.target.closest('.form-close');
 510 |         if (!form) { return; }
 511 |         ev.preventDefault();
 512 |         post(form, form.action, { counted: val(form, 'counted'), fingerprint: form._fingerprint || null }, true).then(function (data) { if (data) { liveSaved(form); } });
```

### KS14 — Periksa rekap kedaluwarsa di bawah kunci shift

closeShift mengunci shift lalu membandingkan hash jika fingerprint diberikan. Hash memuat status/modal, mutasi laci berurutan, id/metode/total sale, dan id pengajuan cash proposed pada shift. Perubahan snapshot menghasilkan penolakan “Rekap sudah berubah ... tutup ulang” tanpa menutup. Fingerprint opsional, jadi klien tanpa token atau fetch gagal tidak mendapat penjaga snapshot ini; closeShift tetap menghitung expected terbaru sendiri.

Nominal dan token opsional — `app/Services/V2/Operations.php:132-140`

```text
 132 |     public function closeShift(int $actor, int $shiftId, string $counted, ?string $fingerprint = null): array
 133 |     {
 134 |         $this->requireRole($actor, ['admin', 'cashier']);
 135 |         $count = $this->amount($counted, 2, 'Uang hitung');
 136 |         if ($count->isNegative()) {
 137 |             $this->invalid('Uang hitung tidak boleh negatif.');
 138 |         }
 139 |         if ($fingerprint !== null && ! preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
 140 |             $this->invalid('Tanda rekap tidak valid.');
```

Kunci, kepemilikan dan stale guard — `app/Services/V2/Operations.php:143-155`

```text
 143 |         return DB::transaction(function () use ($actor, $shiftId, $count, $fingerprint) {
 144 |             $shift = DB::table('v2_shifts')->where('id', $shiftId)->lockForUpdate()->first();
 145 |             if (! $shift) {
 146 |                 $this->invalid('Shift tidak ditemukan.');
 147 |             }
 148 |             if ($shift->status !== 'open') {
 149 |                 $this->invalid('Shift tidak dalam status terbuka.');
 150 |             }
 151 |             if ($this->role($actor) !== 'admin' && (int) $shift->user_id !== $actor) {
 152 |                 abort(403);
 153 |             }
 154 |             if ($fingerprint !== null && ! hash_equals($this->closingFingerprint($shiftId), $fingerprint)) {
 155 |                 $this->invalid('Rekap sudah berubah sejak popup dibuka; tutup ulang untuk melihat angka terbaru.');
```

Isi fingerprint penutupan — `app/Services/V2/Operations.php:193-207`

```text
 193 |     public function closingFingerprint(int $shiftId): string
 194 |     {
 195 |         $shift = DB::table('v2_shifts')->where('id', $shiftId)->first(['status', 'opening_cash']);
 196 |         $movements = DB::table('v2_shift_movements')->where('shift_id', $shiftId)->orderBy('id')->get(['id', 'direction', 'kind', 'amount']);
 197 |         $sales = DB::table('v2_sales')->where('shift_id', $shiftId)->orderBy('id')->get(['id', 'payment_method', 'total']);
 198 |         $pending = DB::table('v2_payments')->where('shift_id', $shiftId)->where('method', 'cash')->where('status', 'proposed')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
 199 | 
 200 |         return hash('sha256', json_encode([
 201 |             'shift' => $shiftId,
 202 |             'status' => $shift->status ?? 'missing',
 203 |             'opening' => $shift ? (string) Money::value((string) $shift->opening_cash, 2) : '0.00',
 204 |             'movements' => $movements->map(fn ($m) => ['id' => (int) $m->id, 'direction' => $m->direction, 'kind' => $m->kind, 'amount' => (string) Money::value((string) $m->amount, 2)])->all(),
 205 |             'sales' => $sales->map(fn ($s) => ['id' => (int) $s->id, 'method' => $s->payment_method, 'total' => (string) Money::value((string) $s->total, 2)])->all(),
 206 |             'pending' => $pending,
 207 |         ], JSON_UNESCAPED_SLASHES));
```

### KS15 — Tutup shift: cocok menjadi closed, selisih menjadi review

Server menjumlah v2_shift_movements pada shift: expected=opening_cash+in−out; difference=counted−expected. Nol -> closed, selain nol -> review. Simpan expected_cash, counted_cash, difference, status, closed_at, hapus open_user_id; hasil JSON mengembalikan expected/counted/difference/status. Tidak ada jurnal selisih, pemindahan kas, pembalikan sale atau perubahan payment pada closeShift. Review bukan open, sehingga tidak dapat menjadi shift jual aktif ataupun asal carry sebelum diresolusi.

Hitung expected dan status hasil — `app/Services/V2/Operations.php:158-171`

```text
 158 |             $in = Money::value('0');
 159 |             $out = Money::value('0');
 160 |             foreach (DB::table('v2_shift_movements')->where('shift_id', $shiftId)->get(['direction', 'amount']) as $movement) {
 161 |                 if ($movement->direction === 'in') {
 162 |                     $in = $in->plus((string) $movement->amount);
 163 |                 } else {
 164 |                     $out = $out->plus((string) $movement->amount);
 165 |                 }
 166 |             }
 167 | 
 168 |             $expected = Money::value((string) $shift->opening_cash, 2)
 169 |                 ->plus((string) $in)->minus((string) $out)->toScale(2, RoundingMode::HalfUp);
 170 |             $difference = $count->minus($expected)->toScale(2, RoundingMode::HalfUp);
 171 |             $status = $difference->isZero() ? 'closed' : 'review';
```

Simpan penutupan dan kembalikan hasil — `app/Services/V2/Operations.php:173-188`

```text
 173 |             DB::table('v2_shifts')->where('id', $shiftId)->update([
 174 |                 'expected_cash' => $this->str($expected),
 175 |                 'counted_cash' => $this->str($count),
 176 |                 'difference' => $this->str($difference),
 177 |                 'status' => $status,
 178 |                 'open_user_id' => null,
 179 |                 'closed_at' => now(),
 180 |                 'updated_at' => now(),
 181 |             ]);
 182 | 
 183 |             return [
 184 |                 'expected' => $this->str($expected),
 185 |                 'counted' => $this->str($count),
 186 |                 'difference' => $this->str($difference),
 187 |                 'status' => $status,
 188 |             ];
```

Carry hanya dari shift closed dengan uang hitung — `app/Services/V2/Operations.php:78-86`

```text
  78 |                     $prev = DB::table('v2_shifts')->where('id', $carryFrom)->lockForUpdate()->first();
  79 |                     if (! $prev || $prev->status !== 'closed') {
  80 |                         $this->invalid('Shift asal harus sudah ditutup.');
  81 |                     }
  82 |                     if ((int) $prev->user_id !== $actor) {
  83 |                         abort(403);
  84 |                     }
  85 |                     if ($prev->counted_cash === null) {
  86 |                         $this->invalid('Shift asal belum memiliki uang hitung.');
```

### KS16 — Resolusi selisih oleh admin dan carry ke shift berikutnya

Review -> closed dengan reviewed_by, reviewed_at dan review_note; expected/count/difference historis tidak diganti dan tidak ada jurnal koreksi otomatis. Pemanggilan ulang closed oleh admin dan alasan identik no-op; status lain/closed dengan alasan lain ditolak. Setelah closed, kasir pemilik dapat memakai counted_cash sebagai sumber carry/both pada pembukaan baru KS02 dengan penjaga sekali pakai; bukan otomatis dipindahkan ketika resolve.

Admin, alasan dan replay resolusi — `app/Services/V2/Operations.php:211-228`

```text
 211 |     {
 212 |         $this->requireRole($actor, ['admin']);
 213 |         $note = trim($reason);
 214 |         $length = mb_strlen($note);
 215 |         if ($length < 5 || $length > 1000) {
 216 |             $this->invalid('Alasan resolusi 5 sampai 1000 karakter.');
 217 |         }
 218 | 
 219 |         DB::transaction(function () use ($actor, $shiftId, $note) {
 220 |             $shift = DB::table('v2_shifts')->where('id', $shiftId)->lockForUpdate()->first();
 221 |             if (! $shift) {
 222 |                 $this->invalid('Shift tidak ditemukan.');
 223 |             }
 224 |             if ($shift->status !== 'review') {
 225 |                 if ($shift->status === 'closed' && (int) $shift->reviewed_by === $actor && $shift->review_note === $note) {
 226 |                     return;
 227 |                 }
 228 |                 $this->invalid('Hanya shift berstatus review yang dapat diresolusi.');
```

Hanya status dan audit review diperbarui — `app/Services/V2/Operations.php:231-237`

```text
 231 |             DB::table('v2_shifts')->where('id', $shiftId)->update([
 232 |                 'status' => 'closed',
 233 |                 'reviewed_by' => $actor,
 234 |                 'reviewed_at' => now(),
 235 |                 'review_note' => $note,
 236 |                 'updated_at' => now(),
 237 |             ]);
```

UI pemeriksaan selisih dan endpoint resolve — `resources/views/v2/sprint2.blade.php:58-72`

```text
  58 | <section class="card" aria-labelledby="review-heading">
  59 | <h2 id="review-heading">Pemeriksaan selisih shift</h2>
  60 | @php $reviewShifts = collect($shifts)->filter(fn ($s) => ($s->status ?? '') === 'review')->values()->all(); @endphp
  61 | <div data-live-list>
  62 | @if (count($reviewShifts) === 0)
  63 |     <p class="note">Tidak ada selisih shift yang perlu diperiksa.</p>
  64 | @else
  65 |     @foreach ($reviewShifts as $shift)
  66 |     <form class="v2-json-form" action="{{ route('v2.shifts.resolve', $shift->id) }}">
  67 |         <p>Shift #{{ $shift->id }}: fisik {{ $shift->counted_cash }}; selisih {{ $shift->difference }}. Angka historis tidak diubah.</p>
  68 |         <div class="field">
  69 |             <label for="review-{{ $shift->id }}">Catatan pemeriksaan</label>
  70 |             <input id="review-{{ $shift->id }}" name="reason" required minlength="5" maxlength="1000">
  71 |         </div>
  72 |         <button type="submit">Catat pemeriksaan admin</button>
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

Replay, final diterima, dan transisi sent — `app/Services/V2/DeliveryTrips.php:46-65`

```text
  46 |             $delivery = $this->assignment->delivery($deliveryId);
  47 |             $this->access($actor, $delivery);
  48 | 
  49 |             if ($delivery->status === 'sent') {
  50 |                 $this->assignment->assertMatches($delivery, $driverId, $vehicleId);
  51 | 
  52 |                 return; // kirim ulang identik: tanpa efek samping
  53 |             }
  54 |             if ($delivery->status === 'received') {
  55 |                 $this->invalid('Pengiriman yang sudah diterima tidak dapat dikirim ulang.');
  56 |             }
  57 | 
  58 |             $this->assignment->resolve($driverId, $vehicleId, $delivery);
  59 | 
  60 |             DB::table('v2_deliveries')->where('id', $deliveryId)->update([
  61 |                 'status' => 'sent',
  62 |                 'dispatched_by' => $actor,
  63 |                 'dispatched_at' => now(),
  64 |                 'updated_at' => now(),
  65 |             ]);
```

Guard akses nyata admin atau pemilik sale — `app/Services/V2/DeliveryTrips.php:365-377`

```text
 365 |     private function access(int $actor, object $delivery): void
 366 |     {
 367 |         $role = DB::table('v2_user_roles')->where('user_id', $actor)->value('role');
 368 |         if ($role === 'admin') {
 369 |             return;
 370 |         }
 371 |         if ($role === 'cashier') {
 372 |             $owner = DB::table('v2_sales')->where('id', $delivery->sale_id)->value('user_id');
 373 |             if ((int) $owner === $actor) {
 374 |                 return;
 375 |             }
 376 |         }
 377 |         abort(403);
```

### PG3 — Konfirmasi barang kiriman diterima

Status received dan received_by/at tersimpan. Terima ulang received tidak berefek; prepared ditolak karena belum dikirim. Ini penerimaan pelanggan, BUKAN penerimaan stok supplier; tidak menulis stok/jurnal/pembayaran.

Sent wajib sebelum received; audit penerimaan — `app/Services/V2/DeliveryTrips.php:79-94`

```text
  79 |             $delivery = $this->assignment->delivery($deliveryId);
  80 |             $this->access($actor, $delivery);
  81 | 
  82 |             if ($delivery->status === 'received') {
  83 |                 return; // terima ulang identik: tanpa efek samping
  84 |             }
  85 |             if ($delivery->status !== 'sent') {
  86 |                 $this->invalid('Pengiriman harus dikirim terlebih dahulu sebelum diterima.');
  87 |             }
  88 | 
  89 |             DB::table('v2_deliveries')->where('id', $deliveryId)->update([
  90 |                 'status' => 'received',
  91 |                 'received_by' => $actor,
  92 |                 'received_at' => now(),
  93 |                 'updated_at' => now(),
  94 |             ]);
```

### PG4 — Catat biaya perjalanan langsung

v2_trips posted; jurnal DR 6101 beban perjalanan, CR 1101 tunai atau 1102 bank. Cash memerlukan shift admin terbuka, saldo laci cukup, mutasi out adjustment; transfer tanpa mutasi laci. Keduanya memeriksa saldo akun. Replay muatan identik mengembalikan hasil tersimpan; kunci dipakai muatan berbeda ditolak. Tidak mengubah stok; beban ini bukan pengurang langsung pendapatan ongkir 4102.

Cash memerlukan shift dan laci cukup — `app/Services/V2/SupplyTrips.php:311-329`

```text
 311 |                 if ($method === 'cash') {
 312 |                     $shift = DB::table('v2_shifts')
 313 |                         ->where('user_id', $actor)
 314 |                         ->where('status', 'open')
 315 |                         ->lockForUpdate()
 316 |                         ->first();
 317 |                     if (! $shift) {
 318 |                         $this->invalid('Biaya tunai wajib memakai shift admin yang sedang terbuka.');
 319 |                     }
 320 | 
 321 |                     $drawer = Money::value((string) $shift->opening_cash);
 322 |                     foreach (DB::table('v2_shift_movements')->where('shift_id', $shift->id)->get(['direction', 'amount']) as $movement) {
 323 |                         $drawer = $movement->direction === 'in'
 324 |                             ? $drawer->plus((string) $movement->amount)
 325 |                             : $drawer->minus((string) $movement->amount);
 326 |                     }
 327 |                     if ($drawer->isLessThan($total)) {
 328 |                         $this->invalid('Saldo laci kas tidak cukup untuk biaya perjalanan.');
 329 |                     }
```

Jurnal biaya dan mutasi laci bersyarat — `app/Services/V2/SupplyTrips.php:355-374`

```text
 355 |                 $journalId = Ledger::post('v2_trip', $tripId, 'Biaya perjalanan '.$destination, [
 356 |                     ['account_code' => self::TRIP_EXPENSE, 'debit' => $this->str($total), 'credit' => '0.00'],
 357 |                     ['account_code' => $account, 'debit' => '0.00', 'credit' => $this->str($total)],
 358 |                 ], $date);
 359 | 
 360 |                 DB::table('v2_trips')->where('id', $tripId)->update([
 361 |                     'journal_entry_id' => $journalId,
 362 |                     'updated_at' => now(),
 363 |                 ]);
 364 | 
 365 |                 if ($shift !== null) {
 366 |                     DB::table('v2_shift_movements')->insert([
 367 |                         'shift_id' => (int) $shift->id,
 368 |                         'direction' => 'out',
 369 |                         'kind' => 'adjustment',
 370 |                         'amount' => $this->str($total),
 371 |                         'reference_type' => 'v2_trip',
 372 |                         'reference_id' => $tripId,
 373 |                         'note' => 'Biaya perjalanan '.$destination.' (pengeluaran trip, bukan penyesuaian umum)',
 374 |                         'created_at' => now(),
```

### PG5 — Keluarkan uang muka sopir

Uang muka status open dan referensi jurnal tersimpan; DR 1191 aset uang muka, CR 1101 atau 1102. Cash menulis mutasi out pada shift admin; transfer tidak. Belum membebankan 6101 saat uang muka keluar. Replay sama tidak mengeluarkan uang dua kali.

Sumber uang muka dan penjaga saldo — `app/Services/V2/DeliveryTrips.php:151-161`

```text
 151 |                 $account = $method === 'cash' ? self::CASH : self::BANK;
 152 |                 $shift = null;
 153 |                 if ($method === 'cash') {
 154 |                     $shift = $this->openShift($actor);
 155 |                     if ($this->drawerBalance($shift)->isLessThan($amount)) {
 156 |                         $this->invalid('Saldo laci kas tidak cukup untuk uang muka ini.');
 157 |                     }
 158 |                 }
 159 | 
 160 |                 $this->requireAccount(self::ADVANCE_ASSET);
 161 |                 $this->guardAccount($account, $amount);
```

Posting aset uang muka dan laci — `app/Services/V2/DeliveryTrips.php:182-199`

```text
 182 |                 $journalId = Ledger::post('v2_driver_advance', $advanceId, 'Uang muka sopir '.$driver->name.' '.$date, [
 183 |                     ['account_code' => self::ADVANCE_ASSET, 'debit' => $this->str($amount), 'credit' => '0.00'],
 184 |                     ['account_code' => $account, 'debit' => '0.00', 'credit' => $this->str($amount)],
 185 |                 ], $date);
 186 | 
 187 |                 DB::table('v2_driver_advances')->where('id', $advanceId)->update([
 188 |                     'journal_entry_id' => $journalId,
 189 |                     'updated_at' => $now,
 190 |                 ]);
 191 | 
 192 |                 if ($shift !== null) {
 193 |                     DB::table('v2_shift_movements')->insert([
 194 |                         'shift_id' => (int) $shift->id,
 195 |                         'direction' => 'out',
 196 |                         'kind' => 'adjustment',
 197 |                         'amount' => $this->str($amount),
 198 |                         'reference_type' => 'v2_driver_advance',
 199 |                         'reference_id' => $advanceId,
```

### PG6 — Periksa biaya aktual dan sisa uang muka

Refund dihitung amount - actual. Sisa positif wajib refund_received=true dan refund_method cash/transfer; tunai kembali memerlukan shift admin penerima terbuka. Biaya aktual lebih besar ditolak, bukan otomatis menambah pengeluaran. Gagal validasi menggagalkan transaksi tanpa settlement.

Advance terkunci, batas aktual, dan bukti sisa diterima — `app/Services/V2/DeliveryTrips.php:259-280`

```text
 259 |                 $advance = DB::table('v2_driver_advances')->where('id', $advanceId)->lockForUpdate()->first();
 260 |                 if (! $advance) {
 261 |                     $this->invalid('Uang muka tidak ditemukan.');
 262 |                 }
 263 |                 if ($advance->status === 'settled') {
 264 |                     $this->invalid('Uang muka ini sudah diselesaikan.');
 265 |                 }
 266 | 
 267 |                 $this->requireAccount(self::ADVANCE_ASSET);
 268 |                 $advanceAmount = Money::value((string) $advance->amount);
 269 |                 if ($actual->isGreaterThan($advanceAmount)) {
 270 |                     $this->invalid('Biaya aktual melebihi uang muka; selisih lebih harus dicatat sebagai pengeluaran terpisah (belum didukung).');
 271 |                 }
 272 | 
 273 |                 $refund = $advanceAmount->minus($actual);
 274 |                 if ($refund->isPositive()) {
 275 |                     if (! $refundReceived) {
 276 |                         $this->invalid('Sisa uang muka '.$this->str($refund).' harus dikonfirmasi dengan refund_received=true.');
 277 |                     }
 278 |                     if ($refundMethod === null) {
 279 |                         $this->invalid('Metode pengembalian sisa wajib diisi (cash atau transfer).');
 280 |                     }
```

### PG7 — Posting pertanggungjawaban dan kunci settled

Buat v2_driver_advance_settlements settled. DR 6101 sebesar aktual positif + DR kas/bank sebesar refund positif; CR 1191 sebesar seluruh uang muka. Kaitkan jurnal lalu advance.status=settled. Refund cash menulis mutasi in pada shift admin penerima; bukan wajib shift pengaju awal. Tidak membuat v2_trips kedua. Replay key+muatan sama mengembalikan hasil; key baru untuk advance settled ditolak.

Komposisi jurnal aktual dan pengembalian — `app/Services/V2/DeliveryTrips.php:308-322`

```text
 308 |                 $lines = [];
 309 |                 if ($actual->isPositive()) {
 310 |                     $lines[] = ['account_code' => self::TRIP_EXPENSE, 'debit' => $this->str($actual), 'credit' => '0.00'];
 311 |                 }
 312 |                 if ($refund->isPositive()) {
 313 |                     $lines[] = ['account_code' => $account, 'debit' => $this->str($refund), 'credit' => '0.00'];
 314 |                 }
 315 |                 $lines[] = ['account_code' => self::ADVANCE_ASSET, 'debit' => '0.00', 'credit' => $this->str($advanceAmount)];
 316 | 
 317 |                 $journalId = Ledger::post(
 318 |                     'v2_driver_advance_settlement',
 319 |                     $settlementId,
 320 |                     'Penyelesaian uang muka sopir '.$advance->driver_name,
 321 |                     $lines
 322 |                 );
```

Settled dan refund ke laci penerima — `app/Services/V2/DeliveryTrips.php:324-344`

```text
 324 |                 DB::table('v2_driver_advance_settlements')->where('id', $settlementId)->update([
 325 |                     'journal_entry_id' => $journalId,
 326 |                     'updated_at' => now(),
 327 |                 ]);
 328 |                 DB::table('v2_driver_advances')->where('id', $advanceId)->update([
 329 |                     'status' => 'settled',
 330 |                     'updated_at' => now(),
 331 |                 ]);
 332 | 
 333 |                 if ($shift !== null) {
 334 |                     DB::table('v2_shift_movements')->insert([
 335 |                         'shift_id' => (int) $shift->id,
 336 |                         'direction' => 'in',
 337 |                         'kind' => 'adjustment',
 338 |                         'amount' => $this->str($refund),
 339 |                         'reference_type' => 'v2_driver_advance_settlement',
 340 |                         'reference_id' => $settlementId,
 341 |                         'note' => 'Sisa uang muka sopir '.$advance->driver_name,
 342 |                         'created_at' => now(),
 343 |                         'updated_at' => now(),
 344 |                     ]);
```

## 05-jurnal-laporan — Jurnal, laporan harian, dan laporan keuangan

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

### JL2 — Baca laporan operasional harian

Baca seluruh penjualan dalam shift terpilih, pisahkan pendapatan barang/diskon/ongkir; expected=opening+seluruh in-seluruh out. Collections hanya in jenis sale/collection, bukan seluruh penjualan kredit/transfer. Counted/difference null bila belum dihitung. Bank posted memakai tanggal jurnal; pending/proposed memakai tanggal pengajuan. Laporan read-only, tidak menutup periode/jurnal.

Ruang lingkup admin/kasir dan hari bisnis shift — `app/Services/V2/ReportsDocuments.php:43-58`

```text
  43 |         $this->assertDate($date);
  44 |         $role = $this->operations->role($actor);
  45 |         $own = $role !== 'admin';
  46 | 
  47 |         $shiftQuery = DB::table('v2_shifts')
  48 |             ->whereDate('opened_at', $date)
  49 |             ->when($own, fn ($q) => $q->where('user_id', $actor));
  50 |         $shifts = $shiftQuery->get(['id', 'opening_cash', 'expected_cash', 'counted_cash', 'difference', 'status']);
  51 |         $shiftIds = $shifts->pluck('id')->map(fn ($id) => (int) $id)->all();
  52 | 
  53 |         $sales = $shiftIds === []
  54 |             ? collect()
  55 |             : DB::table('v2_sales')
  56 |                 ->whereIn('shift_id', $shiftIds)
  57 |                 ->when($own, fn ($q) => $q->where('user_id', $actor))
  58 |                 ->get(['subtotal', 'line_discount', 'invoice_discount', 'delivery_fee', 'total']);
```

Expected dan perbedaan tanggal posted/pending bank — `app/Services/V2/ReportsDocuments.php:120-134`

```text
 120 |         $expected = $opening->plus($movementsIn)->minus($out);
 121 | 
 122 |         $posted = $this->zero();
 123 |         $pending = $this->zero();
 124 |         $payments = DB::table('v2_payments')
 125 |             ->join('v2_invoices', 'v2_invoices.id', '=', 'v2_payments.invoice_id')
 126 |             ->where('v2_invoices.direction', 'receivable')
 127 |             ->where('v2_payments.method', 'transfer')
 128 |             ->leftJoin('journal_entries', 'journal_entries.id', '=', 'v2_payments.journal_entry_id')
 129 |             ->where(function ($q) use ($date) {
 130 |                 $q->where(function ($posted) use ($date) { $posted->where('v2_payments.status', 'posted')->whereDate('journal_entries.transaction_date', $date); })
 131 |                   ->orWhere(function ($pending) use ($date) { $pending->whereIn('v2_payments.status', ['pending','proposed'])->whereDate('v2_payments.created_at', $date); });
 132 |             })
 133 |             ->when($own, fn ($q) => $q->where('v2_payments.requested_by', $actor))
 134 |             ->get(['v2_payments.amount', 'v2_payments.status']);
```

### JL3 — Buka nota dan surat jalan penjualan

Baca snapshot sale/items beserta pelanggan dan pengiriman untuk cetak; hitung goods_net=total-delivery_fee dari snapshot. Tidak mengubah stok, status pengiriman, piutang atau jurnal; membuka surat jalan bukan otomatis dispatch/receive.

Guard pemilik dan snapshot item penjualan — `app/Services/V2/ReportsDocuments.php:183-196`

```text
 183 |         $role = $this->operations->role($actor);
 184 | 
 185 |         $sale = DB::table('v2_sales')->where('id', $saleId)->first();
 186 |         if (! $sale) {
 187 |             $this->fail('Penjualan tidak ditemukan.');
 188 |         }
 189 |         if ($role !== 'admin' && (int) $sale->user_id !== $actor) {
 190 |             abort(403);
 191 |         }
 192 | 
 193 |         $items = DB::table('v2_sale_items')
 194 |             ->where('sale_id', $saleId)
 195 |             ->orderBy('id')
 196 |             ->get(['product_id', 'name', 'quantity', 'unit_price', 'cost_price', 'discount', 'subtotal'])
```

Surat jalan harus punya pengiriman sale yang sama — `app/Services/V2/ReportsDocuments.php:216-228`

```text
 216 |         $deliveryRow = DB::table('v2_deliveries')->where('sale_id', $saleId)->first();
 217 |         $delivery = $deliveryRow === null ? null : [
 218 |             'id' => (int) $deliveryRow->id,
 219 |             'number' => (string) $deliveryRow->number,
 220 |             'driver_name' => $deliveryRow->driver_name === null ? null : (string) $deliveryRow->driver_name,
 221 |             'destination' => (string) $deliveryRow->destination,
 222 |             'status' => (string) $deliveryRow->status,
 223 |             'vehicle_id' => $deliveryRow->vehicle_id === null ? null : (int) $deliveryRow->vehicle_id,
 224 |         ];
 225 | 
 226 |         if ($kind === 'delivery' && $delivery === null) {
 227 |             $this->fail('Penjualan ini tidak memiliki data pengiriman.');
 228 |         }
```

### JL4 — Baca buku jurnal dan feed pembaruan

Baca header dan baris jurnal tersimpan; saldo kas/bank dari AccountBalanceService; feed after_id membaca jurnal lebih baru, tidak menulis jurnal. POST penyesuaian/saldo-awal legacy ditolak saat cutover, walaupun handler lama masih ada di source. Ini batas V2, bukan jaminan rute lama hilang.

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

Feed jurnal hanya membaca — `app/Http/Controllers/JournalController.php:36-45`

```text
  36 |     public function feed(Request $request): JsonResponse
  37 |     {
  38 |         $validated = $request->validate(['after_id' => 'nullable|integer|min:0']);
  39 |         $afterId = (int) ($validated['after_id'] ?? 0);
  40 |         $entries = $this->entries(['after_id' => $afterId], 25, $afterId > 0);
  41 | 
  42 |         return response()->json([
  43 |             'entries' => $entries,
  44 |             'latest_id' => (int) ($entries->max('id') ?? ($validated['after_id'] ?? 0)),
  45 |         ], 200, ['Cache-Control' => 'no-store, private']);
```

### JL5 — Hitung laba rugi dari baris jurnal

Kelompokkan 4xxx pendapatan, 5xxx HPP, 6xxx beban, 7xxx penghasilan lain, 8xxx beban lain. Pendapatan=kredit-debit; beban=debit-kredit. Laba bersih=pendapatan-HPP-beban+penghasilan lain-beban lain. Semua perhitungan BigDecimal; keluaran HTML/JSON atau CSV terpisah, tanpa menulis saldo atau menutup buku.

Sumber baris jurnal dan filter tanggal laba rugi — `app/Services/AccountingReportService.php:37-55`

```text
  37 |     protected function fetchProfitAndLossLines(?string $startDate = null, ?string $endDate = null): Collection
  38 |     {
  39 |         $query = DB::table('journal_entry_lines')
  40 |             ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
  41 |             ->leftJoin('chart_of_accounts', 'journal_entry_lines.account_code', '=', 'chart_of_accounts.account_code');
  42 | 
  43 |         if ($startDate) {
  44 |             $query->where('journal_entries.transaction_date', '>=', $startDate);
  45 |         }
  46 |         if ($endDate) {
  47 |             $query->where('journal_entries.transaction_date', '<=', $endDate);
  48 |         }
  49 | 
  50 |         return $query->select(
  51 |             'journal_entry_lines.account_code',
  52 |             DB::raw('COALESCE(MAX(chart_of_accounts.account_name), MAX(journal_entry_lines.account_name), journal_entry_lines.account_code) as account_name'),
  53 |             DB::raw('MAX(chart_of_accounts.account_type) as coa_type'),
  54 |             DB::raw('SUM(journal_entry_lines.debit) as total_debit'),
  55 |             DB::raw('SUM(journal_entry_lines.credit) as total_credit')
```

Rumus laba dan hasil terformat — `app/Services/AccountingReportService.php:169-185`

```text
 169 |         $grossProfit = $totalRevenue->minus($totalCogs);
 170 |         $operatingProfit = $grossProfit->minus($totalExpense);
 171 |         $netProfit = $operatingProfit->plus($totalOtherIncome)->minus($totalOtherExpense);
 172 | 
 173 |         return [
 174 |             'revenues' => $revenues,
 175 |             'total_revenue' => (string) $totalRevenue->toScale(2),
 176 |             'cogs' => $cogs,
 177 |             'total_cogs' => (string) $totalCogs->toScale(2),
 178 |             'gross_profit' => (string) $grossProfit->toScale(2),
 179 |             'expenses' => $expenses,
 180 |             'total_expense' => (string) $totalExpense->toScale(2),
 181 |             'other_income' => $otherIncome,
 182 |             'total_other_income' => (string) $totalOtherIncome->toScale(2),
 183 |             'other_expenses' => $otherExpenses,
 184 |             'total_other_expense' => (string) $totalOtherExpense->toScale(2),
 185 |             'net_profit' => (string) $netProfit->toScale(2),
```

### JL6 — Hitung neraca per tanggal dan keseimbangan

Tambahkan laba sampai as_of_date sebagai laba ditahan pada sisi ekuitas. is_balanced mensyaratkan aset=kewajiban+ekuitas+laba DAN setiap jurnal tidak kosong serta debit=kredit (journalsAreBalanced). Hanya pembacaan, bukan membuat jurnal penutup, neraca saldo baru, atau approval bisnis.

Persamaan neraca plus pemeriksaan setiap jurnal — `app/Services/AccountingReportService.php:280-296`

```text
 280 |         $pl = $this->generateProfitAndLoss(null, $asOfDate);
 281 |         $netProfit = BigDecimal::of((string) ($pl['net_profit'] ?? '0'));
 282 | 
 283 |         $totalLiabilitiesAndEquity = $totalLiability->plus($totalEquity)->plus($netProfit);
 284 |         $totalEquityWithRetained = $totalEquity->plus($netProfit);
 285 | 
 286 |         return [
 287 |             'assets' => $assets,
 288 |             'total_assets' => (string) $totalAsset->toScale(2),
 289 |             'liabilities' => $liabilities,
 290 |             'total_liabilities' => (string) $totalLiability->toScale(2),
 291 |             'equity' => $equity,
 292 |             'retained_earnings' => (string) $netProfit->toScale(2),
 293 |             'total_equity' => (string) $totalEquityWithRetained->toScale(2),
 294 |             'total_liabilities_and_equity' => (string) $totalLiabilitiesAndEquity->toScale(2),
 295 |             'is_balanced' => $totalAsset->isEqualTo($totalLiabilitiesAndEquity) && $this->journalsAreBalanced($asOfDate),
 296 |         ];
```

Jurnal kosong/tidak seimbang menggagalkan status seimbang — `app/Services/AccountingReportService.php:206-222`

```text
 206 |         foreach ($rows as $row) {
 207 |             if ($entryId !== $row->entry_id) {
 208 |                 if ($entryId !== null && ! $debit->isEqualTo($credit)) {
 209 |                     return false;
 210 |                 }
 211 |                 $entryId = $row->entry_id;
 212 |                 $debit = BigDecimal::zero();
 213 |                 $credit = BigDecimal::zero();
 214 |             }
 215 |             if ($row->line_id === null) {
 216 |                 return false;
 217 |             }
 218 |             $debit = $debit->plus((string) $row->debit);
 219 |             $credit = $credit->plus((string) $row->credit);
 220 |         }
 221 | 
 222 |         return $debit->isEqualTo($credit);
```

### JL7 — Hitung arus kas dan rekonsiliasi kas-bank

Kelompok operasi/investasi/pendanaan menurut reference_type lalu akun lawan. Perpindahan internal kas↔bank dengan netCash nol dilewati dari arus bersih. Cek saldo awal+perubahan bersih=saldo akhir DAN jumlah rincian akun=saldo akhir. is_reconciled bukan bukti uang fisik cocok dan bukan pemisahan brankas vs setiap laci, karena sama-sama dapat memakai 1101.

Arus kas neto dan transfer internal — `app/Services/AccountingReportService.php:422-435`

```text
 422 |                 foreach ($entryLines as $l) {
 423 |                     if (in_array($l->account_code, self::CASH_ACCOUNTS, true)) {
 424 |                         $cashDebit = $cashDebit->plus((string) $l->debit);
 425 |                         $cashCredit = $cashCredit->plus((string) $l->credit);
 426 |                     } else {
 427 |                         $nonCashLines[] = $l;
 428 |                     }
 429 |                 }
 430 | 
 431 |                 $netCash = $cashDebit->minus($cashCredit);
 432 |                 if ($netCash->isZero()) {
 433 |                     // Internal transfer between cash and bank: net change is zero
 434 |                     continue;
 435 |                 }
```

Rekonsiliasi saldo awal, arus, saldo akhir, rincian kas-bank — `app/Services/AccountingReportService.php:665-684`

```text
 665 |         $op = $formatActivity($operatingInflows, $operatingOutflows);
 666 |         $inv = $formatActivity($investingInflows, $investingOutflows);
 667 |         $fin = $formatActivity($financingInflows, $financingOutflows);
 668 | 
 669 |         $netCashChange = $op['_net']->plus($inv['_net'])->plus($fin['_net']);
 670 |         $reconciledEnding = $beginningCash->plus($netCashChange);
 671 | 
 672 |         unset($op['_net'], $inv['_net'], $fin['_net']);
 673 | 
 674 |         return [
 675 |             'start_date' => $startDate,
 676 |             'end_date' => $endDate,
 677 |             'beginning_cash' => (string) $beginningCash->toScale(2),
 678 |             'operating_activities' => $op,
 679 |             'investing_activities' => $inv,
 680 |             'financing_activities' => $fin,
 681 |             'net_cash_change' => (string) $netCashChange->toScale(2),
 682 |             'ending_cash' => (string) $endingCash->toScale(2),
 683 |             'cash_breakdown' => $cashBreakdown,
 684 |             'is_reconciled' => $reconciledEnding->isEqualTo($endingCash) && $breakdownSum->isEqualTo($endingCash),
```

