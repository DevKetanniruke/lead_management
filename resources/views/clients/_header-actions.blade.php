@can('create', App\Models\Invoice::class)
    @if ($client->status !== 'archived')
        <a href="{{ route('invoices.create', ['client' => $client->id]) }}"><x-primary-button type="button"><x-icon name="invoice" class="h-4 w-4" /> <span class="hidden sm:inline">New invoice</span></x-primary-button></a>
    @endif
@endcan
