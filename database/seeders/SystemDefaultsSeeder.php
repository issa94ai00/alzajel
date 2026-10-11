<?php

namespace Database\Seeders;

use App\Support\SystemDefaults;
use Illuminate\Database\Seeder;

class SystemDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        app(SystemDefaults::class)->syncAll();
    }
}
