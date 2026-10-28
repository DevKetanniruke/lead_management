<?php

namespace Database\Seeders;

use App\Models\LeadSource;
use Illuminate\Database\Seeder;

class LeadSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            'Website',
            'Referral',
            'Social Media',
            'Google Ads',
            'Facebook / Instagram',
            'LinkedIn',
            'Cold Call',
            'Cold Email',
            'Event / Trade Show',
            'Partner / Reseller',
            'Walk-in',
            'WhatsApp',
            'Email Campaign',
            'Newspaper / Print',
            'TV / Radio',
            'YouTube',
            'SEO / Organic Search',
            'Other',
        ];

        foreach ($sources as $name) {
            LeadSource::firstOrCreate(['name' => $name]);
        }
    }
}
