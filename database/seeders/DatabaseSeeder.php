<?php

namespace Database\Seeders;

use App\Models\ApprovalRule;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);

        ApprovalRule::firstOrCreate(
            ['scope' => 'branch_order'],
            [
                'name' => 'Branch orders above threshold require a second approval',
                'threshold' => 2000000,
                'unit' => 'value',
                'required_role' => 'godown-manager',
                'enabled' => true,
            ],
        );

        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main Branch']);

        $owner = User::firstOrCreate(
            ['email' => 'emanuelmkenza@gmail.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'branch_id' => $branch->id,
            ],
        );
        $owner->forceFill(['branch_id' => $branch->id])->save();
        $owner->assignRole('Owner');
    }
}
