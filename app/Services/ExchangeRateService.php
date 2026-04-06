<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    /**
     * Fetch how many units of $to equal one unit of $from (e.g. 1 USD = X ZAR).
     *
     * @return non-empty-string|null
     */
    public function fetchRate(string $from, string $to): ?string
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return '1';
        }

        $response = Http::timeout(12)
            ->acceptJson()
            ->get('https://api.frankfurter.app/latest', [
                'from' => $from,
                'to' => $to,
            ]);

        if (! $response->successful()) {
            Log::warning('exchange_rate.fetch_failed', [
                'from' => $from,
                'to' => $to,
                'status' => $response->status(),
            ]);

            return null;
        }

        $rate = $response->json('rates.'.$to);

        if ($rate === null || ! is_numeric($rate)) {
            return null;
        }

        return (string) $rate;
    }
}
