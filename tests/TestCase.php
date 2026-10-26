<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function startBooks(string $cash = '1000000000.00', string $bank = '1000000000.00'): void
    {
        $service = app(\App\Services\OpeningBalanceService::class);
        if ($service->current()?->status === 'posted') {
            return;
        }

        $service->saveDraft('2026-01-01', $cash, $bank);
        $service->post();
    }
}
