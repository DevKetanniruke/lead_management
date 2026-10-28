<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Application settings stored in the `settings` table (key → JSON value), cached, with defaults. */
class Settings
{
    private const CACHE_KEY = 'app-settings';

    public const DEFAULTS = [
        // Company
        'company_name' => 'Your Company',
        'company_legal_name' => null,
        'company_address' => null,
        'company_city' => null,
        'company_state' => null,
        'company_state_code' => null,
        'company_postal_code' => null,
        'company_country' => 'India',
        'company_email' => null,
        'company_phone' => null,
        'company_website' => null,
        'company_logo_path' => null,

        // Tax
        'tax_system' => 'gst',          // gst | simple | none
        'tax_label' => 'Tax',           // used by the "simple" system
        'tax_registration_label' => 'GSTIN',
        'tax_registration_number' => null,
        'default_tax_rate' => '18',
        'tax_rates' => ['0', '5', '12', '18', '28'],

        // Invoicing
        'invoice_prefix' => 'INV',
        'invoice_year_format' => 'Y',   // Y (2026) | FY (2026-27) | none
        'invoice_number_padding' => 4,
        'currency' => 'INR',
        'payment_terms_days' => 15,
        'payment_terms_text' => 'Payment due within 15 days of the invoice date.',
        'payment_instructions' => null,
        'invoice_terms' => null,
        'invoice_footer' => 'Thank you for your business.',
        'signatory_name' => null,
        'signature_path' => null,
        'financial_year_start_month' => 4,

        // Payments & reminders
        'payment_methods' => ['Bank Transfer', 'UPI', 'Cash', 'Card', 'Cheque', 'Other'],
        'due_reminder_days' => 3,
    ];

    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                if (! Schema::hasTable('settings')) {
                    return self::DEFAULTS;
                }
                $stored = DB::table('settings')->pluck('value', 'key')->map(fn ($v) => json_decode($v, true))->all();
            } catch (\Throwable) {
                return self::DEFAULTS;
            }

            return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /** @param array<string, mixed> $values only known keys are stored */
    public static function put(array $values): void
    {
        $now = now();
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => $now, 'created_at' => $now]);
        }
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function currency(): string
    {
        return (string) self::get('currency', 'INR');
    }
}
