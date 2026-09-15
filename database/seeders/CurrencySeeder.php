<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'AFN', 'name' => 'Afghan Afghani', 'symbol' => '؋', 'decimal_places' => 2],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨', 'decimal_places' => 2],
            ['code' => 'IRR', 'name' => 'Iranian Rial', 'symbol' => '﷼', 'decimal_places' => 0],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
        ] as $currency) {
            Currency::updateOrCreate(['code' => $currency['code']], $currency);
        }
    }
}
