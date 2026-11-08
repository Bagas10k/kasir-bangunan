<option value="">Pilih sopir terdaftar</option>
@foreach($drivers as $driver)
    <option value="{{ $driver->id }}">{{ $driver->code }} · {{ $driver->employee_name ?? $driver->name }} ({{ $driver->kind === 'internal' ? 'karyawan' : 'luar' }})</option>
@endforeach
