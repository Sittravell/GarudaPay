<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CurrencyService
{
    protected string $baseUrl = 'https://open.er-api.com/v6/latest/USD';

    public function getRates(): array
    {
        return Cache::remember('currency_rates', 3600, function () {
            $response = Http::get($this->baseUrl);
            if ($response->successful()) {
                return $response->json()['rates'] ?? [];
            }
            return [];
        });
    }

    public function convert(float $amount, string $fromCurrency, string $toCurrency = 'USD'): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rates = $this->getRates();

        // USD base
        // If fromCurrency is EUR (rate 0.9), and amount is 10 EUR.
        // To USD: 10 / 0.9 = 11.11 USD.
        // Formula: Amount / Rate(from) * Rate(to)
        // Rate(USD) is 1.

        $fromRate = $rates[$fromCurrency] ?? null;
        $toRate = $rates[$toCurrency] ?? null;

        if (!$fromRate || !$toRate) {
            throw new \Exception("Currency rate not available for conversion.");
        }

        return ($amount / $fromRate) * $toRate;
    }

    public function getAvailableCurrencies(): array
    {
        $rates = $this->getRates();
        return array_keys($rates);
    }
}
