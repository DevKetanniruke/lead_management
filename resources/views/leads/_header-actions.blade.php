@if ($lead->client && auth()->user()->can('create', App\Models\Invoice::class) && auth()->user()->can('view', $lead->client))
    <a href="{{ route('invoices.create', ['lead' => $lead->id]) }}"><x-primary-button type="button"><x-icon name="invoice" class="h-4 w-4" /> Create invoice</x-primary-button></a>
@endif
