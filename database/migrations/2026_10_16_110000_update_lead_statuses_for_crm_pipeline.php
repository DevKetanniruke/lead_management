<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aligns the pipeline with the CRM stages without touching lead data:
 * Proposal → Proposal Sent, Won → Won / Converted, adds Negotiation, and re-orders.
 */
return new class extends Migration
{
    private const RENAMES = ['Proposal' => 'Proposal Sent', 'Won' => 'Won / Converted'];

    private const ORDER = ['New', 'Contacted', 'Qualified', 'Proposal Sent', 'Negotiation', 'Won / Converted', 'Lost'];

    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            if (! DB::table('lead_statuses')->where('name', $to)->exists()) {
                DB::table('lead_statuses')->where('name', $from)->update(['name' => $to, 'updated_at' => now()]);
            }
        }

        if (! DB::table('lead_statuses')->where('name', 'Negotiation')->exists()) {
            DB::table('lead_statuses')->insert([
                'name' => 'Negotiation', 'color' => 'orange', 'position' => 0,
                'is_won' => false, 'is_lost' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Known stages first in CRM order; custom stages keep their relative order after them.
        $known = array_flip(self::ORDER);
        DB::table('lead_statuses')->orderBy('position')->orderBy('id')->get()
            ->sortBy(fn ($s) => [$known[$s->name] ?? 100, $s->position, $s->id])
            ->values()
            ->each(fn ($s, $i) => DB::table('lead_statuses')->where('id', $s->id)->update(['position' => $i + 1]));
    }

    public function down(): void
    {
        foreach (self::RENAMES as $from => $to) {
            if (! DB::table('lead_statuses')->where('name', $from)->exists()) {
                DB::table('lead_statuses')->where('name', $to)->update(['name' => $from]);
            }
        }

        // Negotiation is kept if any lead uses it, so no lead loses its stage.
        $negotiation = DB::table('lead_statuses')->where('name', 'Negotiation')->value('id');
        if ($negotiation && ! DB::table('leads')->where('lead_status_id', $negotiation)->exists()) {
            DB::table('lead_statuses')->where('id', $negotiation)->delete();
        }
    }
};
