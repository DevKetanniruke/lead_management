@php
    $input = 'mt-1 block w-full border-slate-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500';
    $cur = $invoice->currency;
    $action = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50';
@endphp

<x-app-layout :title="'Invoice '.$invoice->number">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <h2 class="truncate text-lg font-semibold text-slate-900">Invoice {{ $invoice->number }}</h2>
                <x-invoice-status :invoice="$invoice" />
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @can('issue', $invoice)
                    <form method="POST" action="{{ route('invoices.issue', $invoice) }}"
                          data-confirm="Once issued, amounts, items and tax can no longer be edited. Corrections require voiding and reissuing." data-confirm-title="Issue {{ $invoice->number }}?" data-confirm-button="Issue invoice" data-confirm-tone="primary">
                        @csrf
                        <x-primary-button><x-icon name="send" class="h-4 w-4" /> Issue</x-primary-button>
                    </form>
                @endcan
                @can('recordPayment', $invoice)
                    @if (Route::has('payments.create'))
                        <a href="{{ route('payments.create', ['invoice' => $invoice->id]) }}"><x-primary-button type="button"><x-icon name="payments" class="h-4 w-4" /> Record payment</x-primary-button></a>
                    @endif
                @endcan
                <a href="{{ route('invoices.pdf', $invoice) }}" class="hidden sm:block"><x-secondary-button><x-icon name="download" class="h-4 w-4" /> PDF</x-secondary-button></a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6" x-data="{ sendOpen: {{ $errors->hasAny(['to', 'cc', 'message']) ? 'true' : 'false' }}, voidOpen: {{ $errors->has('reason') ? 'true' : 'false' }}, voidMode: @js(old('void_mode', 'void')) }">
        <x-flash :error-keys="[]" />

        @if ($invoice->isOverdue())
            <div class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                <x-icon name="alert" class="h-5 w-5 shrink-0" />
                Overdue by {{ $invoice->daysOverdue() }} {{ Str::plural('day', $invoice->daysOverdue()) }} — {{ money($invoice->balance_due, $cur) }} outstanding.
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Preview --}}
            <div class="xl:col-span-2">
                @include('invoices._styles')
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
                    <div style="min-width: 640px;">@include('invoices._document')</div>
                </div>
            </div>

            {{-- Side panel --}}
            <div class="space-y-6">
                <x-card title="Balance">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Grand total</dt><dd class="font-medium">{{ money($invoice->grand_total, $cur) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Amount paid</dt><dd>{{ money($invoice->amount_paid, $cur) }}</dd></div>
                        <div class="flex justify-between border-t border-slate-100 pt-2 text-base"><dt class="font-semibold">Balance due</dt>
                            <dd class="font-semibold {{ $invoice->isOverdue() ? 'text-red-600' : 'text-slate-900' }}">{{ money($invoice->isVoid() ? 0 : $invoice->balance_due, $cur) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Due</dt><dd>{{ $invoice->due_date->format('M j, Y') }}</dd></div>
                    </dl>
                </x-card>

                <x-card title="Actions" :padding="false">
                    <div class="space-y-0.5 p-2">
                        @can('update', $invoice)
                            <a href="{{ route('invoices.edit', $invoice) }}" class="{{ $action }}"><x-icon name="pencil" class="h-4 w-4" /> Edit draft</a>
                        @endcan
                        <a href="{{ route('invoices.pdf', ['invoice' => $invoice, 'inline' => 1]) }}" target="_blank" rel="noopener" class="{{ $action }}"><x-icon name="eye" class="h-4 w-4" /> Preview PDF</a>
                        <a href="{{ route('invoices.pdf', $invoice) }}" class="{{ $action }}"><x-icon name="download" class="h-4 w-4" /> Download PDF</a>
                        <a href="{{ route('invoices.print', ['invoice' => $invoice, 'autoprint' => 1]) }}" target="_blank" rel="noopener" class="{{ $action }}"><x-icon name="printer" class="h-4 w-4" /> Print</a>
                        @can('send', $invoice)
                            <button type="button" @click="sendOpen = !sendOpen" class="{{ $action }}"><x-icon name="mail" class="h-4 w-4" /> Send by email</button>
                        @endcan
                        @can('duplicate', $invoice)
                            <form method="POST" action="{{ route('invoices.duplicate', $invoice) }}">
                                @csrf
                                <button class="{{ $action }}"><x-icon name="copy" class="h-4 w-4" /> Duplicate as new draft</button>
                            </form>
                        @endcan
                        @can('void', $invoice)
                            <button type="button" @click="voidOpen = !voidOpen" class="{{ $action }} text-red-700 hover:bg-red-50"><x-icon name="ban" class="h-4 w-4" /> Void / correct invoice</button>
                        @endcan
                        @can('delete', $invoice)
                            <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" data-confirm="The draft is removed. Its number {{ $invoice->number }} will not be reused." data-confirm-title="Delete this draft?" data-confirm-button="Delete draft">
                                @csrf @method('DELETE')
                                <button class="{{ $action }} text-slate-500 hover:text-red-700"><x-icon name="trash" class="h-4 w-4" /> Delete draft</button>
                            </form>
                        @endcan
                    </div>

                    {{-- Send by email --}}
                    @can('send', $invoice)
                        <form method="POST" action="{{ route('invoices.send', $invoice) }}" x-show="sendOpen" x-cloak class="space-y-3 border-t border-slate-100 p-4">
                            @csrf
                            <div>
                                <x-input-label for="to" value="To *" />
                                <input id="to" name="to" type="email" required class="{{ $input }}" value="{{ old('to', $billing['email'] ?? $invoice->client->email) }}">
                                <x-input-error :messages="$errors->get('to')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="cc" value="CC (comma separated)" />
                                <input id="cc" name="cc" type="text" class="{{ $input }}" value="{{ old('cc', $invoice->client->accountManager?->email) }}">
                                <x-input-error :messages="$errors->get('cc')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="message" value="Message" />
                                <textarea id="message" name="message" rows="3" maxlength="5000" class="{{ $input }}">{{ old('message') }}</textarea>
                            </div>
                            <x-primary-button><x-icon name="send" class="h-4 w-4" /> Send with PDF</x-primary-button>
                            @if ($invoice->sent_at)<p class="text-xs text-slate-500">Last sent {{ $invoice->sent_at->diffForHumans() }}.</p>@endif
                        </form>
                    @endcan

                    {{-- Void / void & reissue --}}
                    @can('void', $invoice)
                        <form method="POST" x-show="voidOpen" x-cloak class="space-y-3 border-t border-slate-100 p-4"
                              :action="voidMode === 'reissue' ? @js(route('invoices.reissue', $invoice)) : @js(route('invoices.void', $invoice))"
                              data-confirm="The invoice stays on record marked VOID with your reason. This cannot be undone." data-confirm-title="Void {{ $invoice->number }}?" data-confirm-button="Void invoice">
                            @csrf
                            <input type="hidden" name="void_mode" :value="voidMode">
                            @if ((float) $invoice->amount_paid > 0)
                                <p class="rounded-lg bg-amber-50 p-3 text-xs text-amber-900">This invoice has payments recorded. Reverse them first; voiding is refused while money is allocated.</p>
                            @endif
                            <fieldset class="space-y-2 text-sm">
                                <label class="flex items-start gap-2"><input type="radio" value="void" x-model="voidMode" class="mt-0.5 text-indigo-600"> <span><strong>Void only</strong> — cancel this invoice.</span></label>
                                @can('reissue', $invoice)
                                    <label class="flex items-start gap-2"><input type="radio" value="reissue" x-model="voidMode" class="mt-0.5 text-indigo-600"> <span><strong>Void &amp; reissue</strong> — cancel it and open a corrected draft with a new number.</span></label>
                                @endcan
                            </fieldset>
                            <div>
                                <x-input-label for="reason" value="Reason *" />
                                <textarea id="reason" name="reason" rows="2" required minlength="5" maxlength="1000" class="{{ $input }}" placeholder="e.g. Wrong GSTIN on invoice">{{ old('reason') }}</textarea>
                                <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                            </div>
                            <x-danger-button><span x-text="voidMode === 'reissue' ? 'Void & reissue' : 'Void invoice'">Void invoice</span></x-danger-button>
                        </form>
                    @endcan
                </x-card>

                <x-card title="Details">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Client</dt>
                            <dd class="text-right">@can('view', $invoice->client)<a href="{{ route('clients.show', $invoice->client) }}#invoices" class="font-medium text-indigo-600 hover:underline">{{ $invoice->client->displayName() }}</a>@else{{ $invoice->client->displayName() }}@endcan</dd></div>
                        @if ($invoice->lead)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Lead</dt><dd class="text-right">#{{ $invoice->lead->id }} {{ $invoice->lead->name }}</dd></div>
                        @endif
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Tax treatment</dt><dd class="text-right">{{ App\Models\Invoice::TAX_TYPE_LABELS[$invoice->tax_type] ?? $invoice->tax_type }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Created</dt><dd class="text-right">{{ $invoice->created_at->format('M j, Y') }} · {{ $invoice->creator?->name ?? '—' }}</dd></div>
                        @if ($invoice->issued_at)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Issued</dt><dd class="text-right">{{ $invoice->issued_at->format('M j, Y H:i') }} · {{ $invoice->issuer?->name }}</dd></div>
                        @endif
                        @if ($invoice->voided_at)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Voided</dt><dd class="text-right">{{ $invoice->voided_at->format('M j, Y H:i') }} · {{ $invoice->voider?->name }}</dd></div>
                        @endif
                        @if ($invoice->amends)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Replaces</dt><dd class="text-right"><a href="{{ route('invoices.show', $invoice->amends) }}" class="text-indigo-600 hover:underline">{{ $invoice->amends->number }}</a></dd></div>
                        @endif
                        @foreach ($invoice->amendments as $a)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Replaced by</dt><dd class="text-right"><a href="{{ route('invoices.show', $a) }}" class="text-indigo-600 hover:underline">{{ $a->number }}</a></dd></div>
                        @endforeach
                        @if ($invoice->duplicatedFrom && ! $invoice->amends)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Copied from</dt><dd class="text-right"><a href="{{ route('invoices.show', $invoice->duplicatedFrom) }}" class="text-indigo-600 hover:underline">{{ $invoice->duplicatedFrom->number }}</a></dd></div>
                        @endif
                    </dl>
                    @if ($invoice->internal_notes)
                        <div class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                            <p class="text-xs font-semibold uppercase tracking-wide">Internal notes</p>
                            <p class="mt-1 whitespace-pre-line">{{ $invoice->internal_notes }}</p>
                        </div>
                    @endif
                </x-card>

                @includeIf('invoices._payments-panel')

                <x-card title="Audit trail" :padding="false">
                    <ol class="divide-y divide-slate-100">
                        @forelse ($history as $entry)
                            <li class="px-5 py-3 text-sm">
                                <p class="text-slate-800">{{ $entry->description }}</p>
                                <p class="text-xs text-slate-500">{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->format('M j, Y H:i') }}</p>
                            </li>
                        @empty
                            <li class="px-5 py-4 text-sm text-slate-500">No entries.</li>
                        @endforelse
                    </ol>
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
