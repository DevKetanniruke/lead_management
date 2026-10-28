<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            LeadStatusSeeder::class,
            LeadSourceSeeder::class,
        ]);

        if (app()->environment('local')) {
            $reps = User::factory(3)->create()->each->assignRole('sales_rep');
            Lead::factory(30)->recycle($reps)->create();
        }
    }
}
