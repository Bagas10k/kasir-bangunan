<?php

namespace Tests\Unit;

use App\Services\V2\Money;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class MoneyDisplayTest extends TestCase
{
    #[TestWith(['0', 'Rp 0'])]
    #[TestWith(['0.00', 'Rp 0'])]
    #[TestWith(['315.00', 'Rp 315'])]
    #[TestWith(['1085330000', 'Rp 1.085.330.000'])]
    #[TestWith(['1234.5', 'Rp 1.234,50'])]
    #[TestWith(['1234.505', 'Rp 1.234,51'])]
    #[TestWith(['-1500.00', '-Rp 1.500'])]
    #[TestWith(['-0.50', '-Rp 0,50'])]
    #[TestWith(['9999999999999999.99', 'Rp 9.999.999.999.999.999,99'])]
    public function test_display_uses_one_rupiah_format(string $input, string $expected): void
    {
        $this->assertSame($expected, Money::display($input));
    }

    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['abc'])]
    #[TestWith([['1']])]
    public function test_display_shows_dash_for_missing_or_invalid_values(mixed $input): void
    {
        $this->assertSame('—', Money::display($input));
    }
}
