<?php

namespace Database\Seeders;

use App\Models\LeadStatus;
use Illuminate\Database\Seeder;

class LeadStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'New',                'color' => 'blue'],
            ['name' => 'Contacted',          'color' => 'indigo'],
            ['name' => 'Qualified',          'color' => 'purple'],
            ['name' => 'Proposal Sent',      'color' => 'yellow'],
            ['name' => 'Proposal Accepted',  'color' => 'teal'],
            ['name' => 'Negotiation',        'color' => 'orange'],
            ['name' => 'Contract Sent',      'color' => 'pink'],
            ['name' => 'On Hold',            'color' => 'gray'],
            ['name' => LeadStatus::WON,      'color' => 'green', 'is_won' => true],
            ['name' => 'Lost',               'color' => 'red',   'is_lost' => true],
            ['name' => 'Unqualified',        'color' => 'slate', 'is_lost' => true],
        ];

        foreach ($statuses as $i => $status) {
            LeadStatus::updateOrCreate(['name' => $status['name']], $status + ['position' => $i + 1]);
        }
    }
}
