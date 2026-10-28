@php
    /** @var \App\Models\Invoice $invoice */
    $cur = $invoice->currency;
    $taxed = $invoice->tax_type !== 'none';
    $gst = in_array($invoice->tax_type, ['cgst_sgst', 'igst'], true);
    $status = $invoice->displayStatus();
    $sellerCity = collect([$seller['city'] ?? null, $seller['postal_code'] ?? null])->filter()->implode(' - ');
    $billCity = collect([$billing['city'] ?? null, $billing['postal_code'] ?? null])->filter()->implode(' - ');
    $taxLabel = $invoice->tax_type === 'simple' ? \App\Support\Settings::get('tax_label', 'Tax') : 'Tax';
@endphp

<div class="inv-doc">
    {{-- Header --}}
    <table>
        <tr>
            <td style="width: 58%;">
                @if ($logo)<img src="{{ $logo }}" alt="" class="logo"><br>@endif
                <div class="brand">{{ $seller['name'] ?? '' }}</div>
                @if (! empty($seller['legal_name']) && $seller['legal_name'] !== $seller['name'])<div class="muted">{{ $seller['legal_name'] }}</div>@endif
                @if (! empty($seller['address']))<div class="pre">{{ $seller['address'] }}</div>@endif
                @if ($sellerCity || ! empty($seller['state']))
                    <div>{{ collect([$sellerCity, $seller['state'] ?? null])->filter()->implode(', ') }}{{ ! empty($seller['state_code']) ? ' ('.$seller['state_code'].')' : '' }}</div>
                @endif
                @if (! empty($seller['country']))<div>{{ $seller['country'] }}</div>@endif
                <div class="muted">{{ collect([$seller['phone'] ?? null, $seller['email'] ?? null, $seller['website'] ?? null])->filter()->implode(' · ') }}</div>
                @if (! empty($seller['tax_number']))<div><strong>{{ $seller['tax_label'] ?? 'GSTIN' }}:</strong> {{ $seller['tax_number'] }}</div>@endif
            </td>
            <td class="right" style="width: 42%;">
                <div class="title">{{ $gst ? 'TAX INVOICE' : 'INVOICE' }}</div>
                <table class="meta" style="width: auto; margin-left: auto; margin-top: 6px;">
                    <tr><td class="label">Invoice no.</td><td class="right"><strong>{{ $invoice->number }}</strong></td></tr>
                    <tr><td class="label">Invoice date</td><td class="right">{{ $invoice->invoice_date->format('d M Y') }}</td></tr>
                    <tr><td class="label">Due date</td><td class="right">{{ $invoice->due_date->format('d M Y') }}</td></tr>
                    @if ($gst && $invoice->place_of_supply)
                        <tr><td class="label">Place of supply</td><td class="right">{{ $billing['state'] ?? '' }} ({{ $invoice->place_of_supply }})</td></tr>
                    @endif
                    @if ($invoice->amends)
                        <tr><td class="label">Replaces</td><td class="right">{{ $invoice->amends->number }}</td></tr>
                    @endif
                </table>
                @if (in_array($status, ['paid', 'void', 'draft', 'overdue'], true))
                    <div class="stamp stamp-{{ $status }}">{{ strtoupper($invoice->displayStatusLabel()) }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Parties --}}
    <table class="section">
        <tr>
            <td style="width: 55%; padding-right: 12px;">
                <div class="box">
                    <div class="box-title">Bill to</div>
                    <div style="font-weight: bold; font-size: 12px;">{{ $billing['company_name'] ?: ($billing['name'] ?? '') }}</div>
                    @if (! empty($billing['company_name']) && ! empty($billing['name']))<div>Attn: {{ $billing['name'] }}</div>@endif
                    @if (! empty($billing['address']))<div class="pre">{{ $billing['address'] }}</div>@endif
                    @if ($billCity || ! empty($billing['state']))
                        <div>{{ collect([$billCity, $billing['state'] ?? null])->filter()->implode(', ') }}{{ ! empty($billing['state_code']) ? ' ('.$billing['state_code'].')' : '' }}</div>
                    @endif
                    @if (! empty($billing['country']))<div>{{ $billing['country'] }}</div>@endif
                    <div class="muted">{{ collect([$billing['phone'] ?? null, $billing['email'] ?? null])->filter()->implode(' · ') }}</div>
                    @if (! empty($billing['gstin']))<div><strong>GSTIN:</strong> {{ $billing['gstin'] }}</div>@endif
                </div>
            </td>
            <td style="width: 45%;">
                <div class="box">
                    <div class="box-title">Summary</div>
                    <table class="meta">
                        <tr><td class="label">Invoice total</td><td class="right nowrap">{{ money($invoice->grand_total, $cur) }}</td></tr>
                        <tr><td class="label">Amount paid</td><td class="right nowrap">{{ money($invoice->amount_paid, $cur) }}</td></tr>
                        <tr><td class="label"><strong>Balance due</strong></td><td class="right nowrap"><strong>{{ money($invoice->isVoid() ? 0 : $invoice->balance_due, $cur) }}</strong></td></tr>
                        @if ($invoice->payment_terms)<tr><td class="label">Terms</td><td class="right">{{ $invoice->payment_terms }}</td></tr>@endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">S.No</th>
                <th>Description</th>
                <th class="right" style="width: 7%;">Qty</th>
                <th class="right" style="width: 11%;">Rate</th>
                <th class="right" style="width: 10%;">Discount</th>
                @if ($taxed)
                    <th class="right" style="width: 12%;">Taxable</th>
                    <th class="right" style="width: 7%;">{{ $gst ? 'GST' : $taxLabel }}</th>
                    <th class="right" style="width: 10%;">Tax amt</th>
                @endif
                <th class="right" style="width: 12%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $n => $item)
                <tr>
                    <td class="muted">{{ $n + 1 }}</td>
                    <td>
                        {{ $item->description }}
                        @if ($item->hsn_sac)<div class="muted" style="font-size: 9px;">HSN/SAC: {{ $item->hsn_sac }}</div>@endif
                    </td>
                    <td class="right">{{ $item->displayQuantity() }}</td>
                    <td class="right nowrap">{{ money($item->unit_price, $cur) }}</td>
                    <td class="right nowrap">{{ (float) $item->discount ? money($item->discount, $cur) : '—' }}</td>
                    @if ($taxed)
                        <td class="right nowrap">{{ money($item->taxable_amount, $cur) }}</td>
                        <td class="right">{{ rtrim(rtrim((string) $item->tax_rate, '0'), '.') }}%</td>
                        <td class="right nowrap">{{ money($item->tax_amount, $cur) }}</td>
                    @endif
                    <td class="right nowrap"><strong>{{ money($item->line_total, $cur) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <table class="totals">
        <tr><td class="muted">Subtotal</td><td class="right nowrap">{{ money($invoice->subtotal, $cur) }}</td></tr>
        @if ((float) $invoice->discount_total)
            <tr><td class="muted">Discount</td><td class="right nowrap">− {{ money($invoice->discount_total, $cur) }}</td></tr>
        @endif
        @if ($taxed)
            <tr><td class="muted">Taxable value</td><td class="right nowrap">{{ money($invoice->taxable_total, $cur) }}</td></tr>
        @endif
        @if ($invoice->tax_type === 'cgst_sgst')
            <tr><td class="muted">CGST</td><td class="right nowrap">{{ money($invoice->cgst_total, $cur) }}</td></tr>
            <tr><td class="muted">SGST</td><td class="right nowrap">{{ money($invoice->sgst_total, $cur) }}</td></tr>
        @elseif ($invoice->tax_type === 'igst')
            <tr><td class="muted">IGST</td><td class="right nowrap">{{ money($invoice->igst_total, $cur) }}</td></tr>
        @elseif ($invoice->tax_type === 'simple')
            <tr><td class="muted">{{ $taxLabel }}</td><td class="right nowrap">{{ money($invoice->tax_total, $cur) }}</td></tr>
        @endif
        <tr class="grand"><td>Grand total</td><td class="right nowrap">{{ money($invoice->grand_total, $cur) }}</td></tr>
        <tr><td class="muted">Amount paid</td><td class="right nowrap">{{ money($invoice->amount_paid, $cur) }}</td></tr>
        <tr class="due"><td>Balance due</td><td class="right nowrap">{{ money($invoice->isVoid() ? 0 : $invoice->balance_due, $cur) }}</td></tr>
    </table>
    <div class="words right">{{ \App\Support\AmountInWords::format($invoice->grand_total, $cur) }}</div>

    {{-- Payment instructions and notes (left); terms and signature (right) --}}
    @php $hasSignature = $signature || ! empty($seller['signatory_name']); @endphp
    @if ($invoice->notes || ! empty($seller['payment_instructions']) || $invoice->terms || $hasSignature)
        <table class="footer-block">
            <tr>
                <td style="width: 50%; padding-right: 12px;">
                    @if (! empty($seller['payment_instructions']) && ! $invoice->isVoid())
                        <div class="box">
                            <div class="box-title">Payment instructions</div>
                            <div class="pre">{{ $seller['payment_instructions'] }}</div>
                        </div>
                    @endif
                    @if ($invoice->notes)
                        <div style="margin-top: 10px;"><div class="box-title">Notes</div><div class="pre">{{ $invoice->notes }}</div></div>
                    @endif
                </td>
                <td style="width: 50%;">
                    @if ($invoice->terms)
                        <div class="box-title">Terms &amp; conditions</div>
                        <div class="pre muted">{{ $invoice->terms }}</div>
                    @endif
                    @if ($hasSignature)
                        <div class="signature">
                            <div>For {{ $seller['name'] ?? '' }}</div>
                            @if ($signature)<img src="{{ $signature }}" alt=""><br>@else<div style="height: 34px;"></div>@endif
                            <span class="line">{{ $seller['signatory_name'] ?: 'Authorised signatory' }}</span>
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    @if ($invoice->isVoid())
        <div class="footer-block box" style="border-color: #fecaca; color: #b91c1c;">
            This invoice was voided on {{ $invoice->voided_at?->format('d M Y') }}{{ $invoice->void_reason ? ': '.$invoice->void_reason : '' }}. It is not payable.
        </div>
    @endif

    @if (! empty($seller['footer']))<div class="thanks">{{ $seller['footer'] }}</div>@endif
</div>
