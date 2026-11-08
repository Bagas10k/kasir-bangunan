<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Satu-satunya pintu masuk aritmetika uang V2. Tidak pernah memakai float.
 * Nilai keluar selalu string desimal dengan skala tetap.
 */
final class Money
{
    /**
     * Parse nilai uang/kuantitas menjadi BigDecimal berskala tetap.
     * Menerima tanda negatif (dipakai mis. selisih kas); batas non-negatif
     * diperiksa pemanggil sesuai konteks.
     */
    public static function value(string $value, int $scale = 2): BigDecimal
    {
        if ($scale < 0 || $scale > 6) {
            throw new InvalidArgumentException('Skala desimal harus antara 0 dan 6.');
        }

        $value = trim($value);

        if (preg_match('/^-?\d{1,17}(\.\d{1,6})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Nilai angka tidak valid: "'.$value.'". Gunakan format desimal tanpa pemisah ribuan.');
        }

        $decimal = BigDecimal::of($value)->toScale($scale, RoundingMode::HalfUp);
        $limit = BigDecimal::of('1'.str_repeat('0', 19 - $scale));
        if ($decimal->abs()->isGreaterThanOrEqualTo($limit)) {
            throw new InvalidArgumentException('Nilai melampaui kapasitas DECIMAL(19,'.$scale.').');
        }

        return $decimal;
    }

    /**
     * Format tampilan Rupiah tunggal untuk seluruh layar: `Rp 1.234.567`, sen hanya bila ada
     * (`Rp 1.234,50`). Murni tampilan; perhitungan tetap memakai string desimal dan BigDecimal.
     */
    public static function display(mixed $value): string
    {
        if ($value === null || $value === '' || ! is_scalar($value)) {
            return '—';
        }

        try {
            $decimal = BigDecimal::of(trim((string) $value))->toScale(2, RoundingMode::HalfUp);
        } catch (\Throwable) {
            return '—';
        }

        $negative = $decimal->isNegative();
        [$integer, $fraction] = explode('.', (string) $decimal->abs());
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $integer);

        return ($negative ? '-' : '').'Rp '.$grouped.($fraction === '00' ? '' : ','.$fraction);
    }

    /**
     * Hitung diskon atas dasar (base) tertentu.
     * $discount: ['type' => 'amount'|'percent', 'value' => string]; array kosong = 0.
     * Persen 0-100, nominal 0..base. Hasil tidak boleh melebihi base.
     */
    public static function discount(string $base, array $discount): string
    {
        $baseValue = self::value($base);

        if ($baseValue->isNegative()) {
            throw new InvalidArgumentException('Dasar diskon tidak boleh negatif.');
        }

        if ($discount === []) {
            return (string) BigDecimal::zero()->toScale(2);
        }

        $type = $discount['type'] ?? null;
        $raw = $discount['value'] ?? '0';

        if (is_float($raw) || ! is_scalar($raw) || is_bool($raw)) {
            throw new InvalidArgumentException('Nilai diskon harus berupa string desimal.');
        }

        if (! in_array($type, ['amount', 'percent'], true)) {
            throw new InvalidArgumentException('Tipe diskon harus "amount" atau "percent".');
        }

        $amount = self::value((string) $raw);

        if ($amount->isNegative()) {
            throw new InvalidArgumentException('Nilai diskon tidak boleh negatif.');
        }

        if ($type === 'percent') {
            if ($amount->isGreaterThan(100)) {
                throw new InvalidArgumentException('Diskon persen tidak boleh melebihi 100.');
            }
            $result = $baseValue->multipliedBy($amount)->dividedBy(100, 2, RoundingMode::HalfUp);
        } else {
            if ($amount->isGreaterThan($baseValue)) {
                throw new InvalidArgumentException('Diskon nominal melebihi dasar harga.');
            }
            $result = $amount->toScale(2, RoundingMode::HalfUp);
        }

        if ($result->isGreaterThan($baseValue) || $result->isNegative()) {
            throw new InvalidArgumentException('Diskon menghasilkan nilai di luar batas.');
        }

        return (string) $result;
    }
}
