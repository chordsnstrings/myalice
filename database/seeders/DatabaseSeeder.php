<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::firstOrCreate(
            ['name' => 'Acme DTC'],
            ['plan' => 'business', 'wallet_balance' => 128.50, 'currency' => 'USD'],
        );

        User::firstOrCreate(
            ['email' => 'demo@myalice.test'],
            [
                'workspace_id' => $workspace->id,
                'name' => 'Alex Morgan',
                'password' => Hash::make('password'),
                'workspace_role' => 'owner',
            ],
        );
    }
}
