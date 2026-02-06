<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Use Http directly or Service. Let's use Http here to keep it self-contained or instantiate service?
        // Service is better for DRY.
        $response = \Illuminate\Support\Facades\Http::get('https://open.er-api.com/v6/latest/USD');

        if (!$response->successful()) {
            $this->command->error('Failed to fetch currencies from API');
            return;
        }

        $rates = $response->json()['rates'] ?? [];
        $codes = array_keys($rates);

        foreach ($codes as $code) {
            try {
                // symfony/intl
                $name = \Symfony\Component\Intl\Currencies::getName($code);
                $symbol = \Symfony\Component\Intl\Currencies::getSymbol($code);
            } catch (\Exception $e) {
                // Skip if unknown to intl or just default?
                $name = $code;
                $symbol = $code;
            }

            // Check if null (intl returns null for some?)
            if (!$name)
                $name = $code;
            if (!$symbol)
                $symbol = $code;

            DB::table('currencies')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'symbol' => $symbol,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
    }
}
