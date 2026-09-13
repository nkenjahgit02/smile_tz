<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Branch',
                'address' => 'Main Street, Dar es Salaam',
                'phone' => '+255 000 000 000',
                'email' => 'main@example.com',
                'currency' => 'TZS',
                'type' => 'godown',
            ],
        );
    }
}
