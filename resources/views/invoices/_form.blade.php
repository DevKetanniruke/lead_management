@php
    $input = 'mt-1 block w-full border-slate-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500';
    $cell = 'block w-full border-slate-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500';
    $oldItems = old('items', $items ?: [['description' => '', 'hsn_sac' => '', 'quantity' => '1', 'unit_price' => '', 'discount' => '', 'tax_rate' => $defaultTaxRate]]);
    $clientStates = $clients->mapWithKeys(fn ($c) => [$c->id => $c->state_code])->all();
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6"
      x-data="invoiceForm({
          items: @js(array_values($oldItems)),
          clientStates: @js($clientStates),
          taxSystem: @js($taxSystem),
          sellerState: @js($sellerStateCode),
          placeOfSupply: @js(old('place_of_supply', $invoice->place_of_supply)),
          currency: @js(old('currency', $invoice->currency)),
          defaultRate: @js($defaultTaxRate),
      })">
    @csrf
    @isset($method) @method($method) @endisset
    <input type="hidden" name="lead_id" value="{{ old('lead_id', $invoice->lead_id) }}">

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <p class="font-medium">Please fix the highlighted fields.</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach (collect($errors->all())->unique()->take(6) as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if ($taxSystem === 'gst' && ! $sellerStateCode)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Your company GST state code isn't set, so CGST/SGST vs IGST can't be decided.
            @can('settings.manage')<a href="{{ route('settings.edit') }}" class="font-medium underline">Update settings</a>@else Ask an administrator to update Settings. @endcan
        </div>
    @endif

    <x-card title="Invoice details">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <x-input-label for="client_id" value="Client *" />
                <select id="client_id" name="client_id" required class="{{ $input }}" @change="clientChanged($event.target.value)">
                    <option value="">Select a client…</option>
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}" @selected(old('client_id', $invoice->client_id) == $c->id)>{{ $c->company_name ?: $c->name }}{{ $c->company_name ? " ({$c->name})" : '' }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="invoice_date" value="Invoice date *" />
                <input id="invoice_date" name="invoice_date" type="date" required class="{{ $input }}" value="{{ old('invoice_date', $invoice->invoice_date?->toDateString()) }}">
                <x-input-error :messages="$errors->get('invoice_date')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="due_date" value="Due date *" />
                <input id="due_date" name="due_date" type="date" required class="{{ $input }}" value="{{ old('due_date', $invoice->due_date?->toDateString()) }}">
                <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="currency" value="Currency *" />
                <select id="currency" name="currency" class="{{ $input }}" x-model="currency">
                    @foreach ($currencies as $code)
                        <option value="{{ $code }}">{{ $code }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('currency')" class="mt-2" />
            </div>
            @if ($taxSystem === 'gst')
                <div>
                    <x-input-label for="place_of_supply" value="Place of supply (state code) *" />
                    <input id="place_of_supply" name="place_of_supply" type="text" maxlength="5" class="{{ $input }}" x-model="placeOfSupply" placeholder="e.g. 27">
                    <p class="mt-1 text-xs" :class="taxType() === 'cgst_sgst' ? 'text-emerald-700' : 'text-indigo-700'" x-text="taxType() === 'cgst_sgst' ? 'Same state as you: CGST + SGST' : 'Different state: IGST'"></p>
                    <x-input-error :messages="$errors->get('place_of_supply')" class="mt-2" />
                </div>
            @endif
            <div class="sm:col-span-2">
                <x-input-label for="payment_terms" value="Payment terms" />
                <input id="payment_terms" name="payment_terms" type="text" maxlength="255" class="{{ $input }}" value="{{ old('payment_terms', $invoice->payment_terms) }}">
                <x-input-error :messages="$errors->get('payment_terms')" class="mt-2" />
            </div>
        </div>
    </x-card>

    <x-card title="Line items" :padding="false">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-3 py-3 text-center" style="width: 3.5rem;">S.No</th>
                        <th class="px-3 py-3" style="min-width: 16rem;">Service / product *</th>
                        <th class="px-3 py-3" style="width: 7rem;">HSN/SAC</th>
                        <th class="px-3 py-3" style="width: 6rem;">Qty *</th>
                        <th class="px-3 py-3" style="width: 8rem;">Unit price *</th>
                        <th class="px-3 py-3" style="width: 7rem;">Discount</th>
                        @if ($taxSystem !== 'none')<th class="px-3 py-3" style="width: 6.5rem;">Tax %</th>@endif
                        <th class="px-3 py-3 text-right" style="width: 8rem;">Amount</th>
                        <th class="px-2 py-3"><span class="sr-only">Remove</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="(item, i) in items" :key="item._key">
                        <tr class="align-top">
                            <td class="px-3 py-2 pt-4 text-center text-xs font-medium text-slate-500" x-text="i + 1"></td>
                            <td class="px-3 py-2"><input type="text" :name="`items[${i}][description]`" x-model="item.description" required maxlength="500" class="{{ $cell }}" aria-label="Description"></td>
                            <td class="px-3 py-2"><input type="text" :name="`items[${i}][hsn_sac]`" x-model="item.hsn_sac" maxlength="20" class="{{ $cell }}" aria-label="HSN/SAC"></td>
                            <td class="px-3 py-2"><input type="text" inputmode="decimal" :name="`items[${i}][quantity]`" x-model="item.quantity" required class="{{ $cell }} text-right" aria-label="Quantity"></td>
                            <td class="px-3 py-2"><input type="text" inputmode="decimal" :name="`items[${i}][unit_price]`" x-model="item.unit_price" required class="{{ $cell }} text-right" aria-label="Unit price"></td>
                            <td class="px-3 py-2"><input type="text" inputmode="decimal" :name="`items[${i}][discount]`" x-model="item.discount" class="{{ $cell }} text-right" aria-label="Discount amount" placeholder="0"></td>
                            @if ($taxSystem !== 'none')
                                <td class="px-3 py-2">
                                    <select :name="`items[${i}][tax_rate]`" x-model="item.tax_rate" class="{{ $cell }}" aria-label="Tax rate">
                                        @foreach ($taxRates as $rate)<option value="{{ $rate }}">{{ $rate }}%</option>@endforeach
                                    </select>
                                </td>
                            @endif
                            <td class="whitespace-nowrap px-3 py-2 pt-4 text-right font-medium text-slate-900" x-text="fmt(line(item).total)"></td>
                            <td class="px-2 py-2 pt-3">
                                <button type="button" @click="remove(i)" x-show="items.length > 1" class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Remove line"><x-icon name="trash" class="h-4 w-4" /></button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        @foreach ($errors->getMessages() as $key => $messages)
            @if (str_starts_with($key, 'items.'))
                <p class="px-5 pt-2 text-sm text-red-600">{{ $messages[0] }}</p>
            @endif
        @endforeach
        <div class="flex flex-col gap-4 border-t border-slate-100 p-5 sm:flex-row sm:items-start sm:justify-between">
            <button type="button" @click="add()" class="inline-flex items-center gap-2 self-start rounded-lg border border-dashed border-indigo-300 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                <x-icon name="plus" class="h-4 w-4" /> Add line
            </button>
            <dl class="w-full space-y-1.5 text-sm sm:w-80">
                <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd x-text="fmt(totals().subtotal)"></dd></div>
                <div class="flex justify-between" x-show="totals().discount > 0"><dt class="text-slate-500">Discount</dt><dd x-text="'− ' + fmt(totals().discount)"></dd></div>
                <template x-if="taxType() === 'cgst_sgst'">
                    <div class="space-y-1.5">
                        <div class="flex justify-between"><dt class="text-slate-500">CGST</dt><dd x-text="fmt(totals().cgst)"></dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">SGST</dt><dd x-text="fmt(totals().sgst)"></dd></div>
                    </div>
                </template>
                <template x-if="taxType() === 'igst'">
                    <div class="flex justify-between"><dt class="text-slate-500">IGST</dt><dd x-text="fmt(totals().igst)"></dd></div>
                </template>
                <template x-if="taxType() === 'simple'">
                    <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd x-text="fmt(totals().tax)"></dd></div>
                </template>
                <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold text-slate-900"><dt>Grand total</dt><dd x-text="fmt(totals().grand)"></dd></div>
                <p class="pt-1 text-xs text-slate-400">Preview. Final amounts are recalculated on save.</p>
            </dl>
        </div>
    </x-card>

    <x-card title="Notes">
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <div>
                <x-input-label for="notes" value="Customer-facing notes" />
                <textarea id="notes" name="notes" rows="4" maxlength="5000" class="{{ $input }}" placeholder="Shown on the invoice">{{ old('notes', $invoice->notes) }}</textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="terms" value="Terms & conditions" />
                <textarea id="terms" name="terms" rows="4" maxlength="10000" class="{{ $input }}">{{ old('terms', $invoice->terms) }}</textarea>
                <x-input-error :messages="$errors->get('terms')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="internal_notes" value="Internal notes" />
                <textarea id="internal_notes" name="internal_notes" rows="4" maxlength="5000" class="{{ $input }} bg-amber-50/40" placeholder="Never shown to the client">{{ old('internal_notes', $invoice->internal_notes) }}</textarea>
                <x-input-error :messages="$errors->get('internal_notes')" class="mt-2" />
            </div>
        </div>
    </x-card>

    <div class="flex items-center gap-4">
        <x-primary-button>{{ $invoice->exists ? 'Save draft' : 'Create draft' }}</x-primary-button>
        <a href="{{ $invoice->exists ? route('invoices.show', $invoice) : route('invoices.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
        <span class="text-xs text-slate-500">Drafts can be edited until issued.</span>
    </div>
</form>

<script>
    function invoiceForm(cfg) {
        let key = 0;
        // Integer maths in hundredths/thousandths so the preview doesn't drift like floats do.
        const cents = (v) => { const n = parseFloat(String(v ?? '').replace(/,/g, '')); return isFinite(n) ? Math.round(n * 100) : 0; };
        const milli = (v) => { const n = parseFloat(String(v ?? '').replace(/,/g, '')); return isFinite(n) ? Math.round(n * 1000) : 0; };
        const pct = (amountCents, rate) => Math.round(amountCents * rate / 100);

        return {
            items: cfg.items.map((i) => ({ _key: ++key, ...i })),
            placeOfSupply: cfg.placeOfSupply || '',
            currency: cfg.currency,
            add() { this.items.push({ _key: ++key, description: '', hsn_sac: '', quantity: '1', unit_price: '', discount: '', tax_rate: cfg.defaultRate }); },
            remove(i) { this.items.splice(i, 1); },
            clientChanged(id) { if (cfg.clientStates[id]) this.placeOfSupply = cfg.clientStates[id]; },
            taxType() {
                if (cfg.taxSystem === 'gst') return cfg.sellerState && cfg.sellerState === (this.placeOfSupply || '').trim().toUpperCase() ? 'cgst_sgst' : 'igst';
                return cfg.taxSystem === 'simple' ? 'simple' : 'none';
            },
            line(item) {
                const sub = Math.round(milli(item.quantity) * cents(item.unit_price) / 1000);
                const taxable = Math.max(0, sub - cents(item.discount));
                const rate = this.taxType() === 'none' ? 0 : parseFloat(item.tax_rate || 0);
                let cgst = 0, sgst = 0, igst = 0, tax = 0;
                if (this.taxType() === 'cgst_sgst') { cgst = sgst = pct(taxable, rate / 2); tax = cgst + sgst; }
                else if (this.taxType() === 'igst') { igst = tax = pct(taxable, rate); }
                else if (this.taxType() === 'simple') { tax = pct(taxable, rate); }
                return { sub, discount: sub - taxable, taxable, cgst, sgst, igst, tax, total: taxable + tax };
            },
            totals() {
                const t = { subtotal: 0, discount: 0, cgst: 0, sgst: 0, igst: 0, tax: 0, grand: 0 };
                this.items.forEach((i) => { const l = this.line(i); t.subtotal += l.sub; t.discount += l.discount; t.cgst += l.cgst; t.sgst += l.sgst; t.igst += l.igst; t.tax += l.tax; t.grand += l.total; });
                return t;
            },
            fmt(c) {
                const locale = this.currency === 'INR' ? 'en-IN' : 'en-US';
                try { return new Intl.NumberFormat(locale, { style: 'currency', currency: this.currency }).format(c / 100); }
                catch (e) { return (c / 100).toFixed(2); }
            },
        };
    }
</script>
