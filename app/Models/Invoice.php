<?php

namespace App\Models;

use App\Support\Money;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stored lifecycle `status` is draft | issued | void. The displayed status is derived consistently:
 *   void → Void, draft → Draft, nothing owed → Paid, past due → Overdue, something paid → Partially Paid, else Issued.
 * Only issued invoices are receivables; drafts and voids never count towards outstanding or overdue amounts.
 */
class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    public const DRAFT = 'draft';

    public const ISSUED = 'issued';

    public const VOID = 'void';

    public const DISPLAY_STATUSES = [
        'draft' => 'Draft',
        'issued' => 'Issued',
        'partially_paid' => 'Partially Paid',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'void' => 'Void',
    ];

    public const STATUS_COLORS = [
        'draft' => 'gray', 'issued' => 'blue', 'partially_paid' => 'yellow',
        'paid' => 'green', 'overdue' => 'red', 'void' => 'gray',
    ];

    public const TAX_TYPE_LABELS = [
        'cgst_sgst' => 'CGST + SGST (intra-state)',
        'igst' => 'IGST (inter-state)',
        'simple' => 'Tax',
        'none' => 'No tax',
    ];

    /** Editable header fields; totals, status and audit columns are only set by the invoice service. */
    protected $fillable = [
        'client_id', 'lead_id', 'invoice_date', 'due_date', 'currency', 'place_of_supply',
        'payment_terms', 'notes', 'internal_notes', 'terms',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'taxable_total' => 'decimal:2',
            'cgst_total' => 'decimal:2',
            'sgst_total' => 'decimal:2',
            'igst_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'seller_details' => 'array',
            'billing_details' => 'array',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'sent_at' => 'datetime',
            'due_reminder_sent_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function duplicatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicated_from_id')->withTrashed();
    }

    public function amends(): BelongsTo
    {
        return $this->belongsTo(self::class, 'amends_invoice_id')->withTrashed();
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(self::class, 'amends_invoice_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class)->latest('id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === self::ISSUED;
    }

    public function isVoid(): bool
    {
        return $this->status === self::VOID;
    }

    /** An issued invoice with money still owed. */
    public function isReceivable(): bool
    {
        return $this->isIssued() && Money::isPositive($this->balance_due);
    }

    public function isOverdue(): bool
    {
        return $this->isReceivable() && $this->due_date->lt(today());
    }

    public function displayStatus(): string
    {
        return match (true) {
            $this->isVoid() => 'void',
            $this->isDraft() => 'draft',
            ! Money::isPositive($this->balance_due) => 'paid',
            $this->due_date->lt(today()) => 'overdue',
            Money::isPositive($this->amount_paid) => 'partially_paid',
            default => 'issued',
        };
    }

    public function displayStatusLabel(): string
    {
        return self::DISPLAY_STATUSES[$this->displayStatus()];
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(today()) : 0;
    }

    /** Seller details frozen at issue, or the current settings for drafts. */
    public function seller(): array
    {
        return $this->seller_details ?? self::currentSellerDetails();
    }

    /** Client billing details frozen at issue, or the client's current details for drafts. */
    public function billing(): array
    {
        return $this->billing_details ?? self::billingDetailsFor($this->client);
    }

    public static function currentSellerDetails(): array
    {
        $s = Settings::all();

        return [
            'name' => $s['company_name'],
            'legal_name' => $s['company_legal_name'],
            'address' => $s['company_address'],
            'city' => $s['company_city'],
            'state' => $s['company_state'],
            'state_code' => $s['company_state_code'],
            'postal_code' => $s['company_postal_code'],
            'country' => $s['company_country'],
            'email' => $s['company_email'],
            'phone' => $s['company_phone'],
            'website' => $s['company_website'],
            'tax_label' => $s['tax_registration_label'],
            'tax_number' => $s['tax_registration_number'],
            'logo_path' => $s['company_logo_path'],
            'signatory_name' => $s['signatory_name'],
            'signature_path' => $s['signature_path'],
            'payment_instructions' => $s['payment_instructions'],
            'footer' => $s['invoice_footer'],
        ];
    }

    public static function billingDetailsFor(?Client $client): array
    {
        return $client ? [
            'name' => $client->name,
            'company_name' => $client->company_name,
            'email' => $client->email,
            'phone' => $client->phone,
            'address' => $client->address,
            'city' => $client->city,
            'state' => $client->state,
            'state_code' => $client->state_code,
            'postal_code' => $client->postal_code,
            'country' => $client->country,
            'gstin' => $client->gstin,
        ] : [];
    }

    /* ---------------- Query scopes ---------------- */

    /** Invoices the user may see: all with invoices.view_all, otherwise those of clients they can see. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('invoices.view_all')) {
            return $query;
        }

        return $query->whereHas('client', fn ($q) => $q->visibleTo($user));
    }

    /** Issued (not draft, not void). */
    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('invoices.status', self::ISSUED);
    }

    /** Issued with a balance still owed. */
    public function scopeReceivable(Builder $query): Builder
    {
        return $query->issued()->where('invoices.balance_due', '>', 0);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->receivable()->whereDate('invoices.due_date', '<', today());
    }

    /** Filter by a derived display status. */
    public function scopeWithDisplayStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'draft' => $query->where('invoices.status', self::DRAFT),
            'void' => $query->where('invoices.status', self::VOID),
            'paid' => $query->issued()->where('invoices.balance_due', '<=', 0),
            'overdue' => $query->overdue(),
            'partially_paid' => $query->receivable()->where('invoices.amount_paid', '>', 0)->whereDate('invoices.due_date', '>=', today()),
            'issued' => $query->receivable()->where('invoices.amount_paid', '<=', 0)->whereDate('invoices.due_date', '>=', today()),
            'unpaid' => $query->receivable(),
            default => $query,
        };
    }
}
