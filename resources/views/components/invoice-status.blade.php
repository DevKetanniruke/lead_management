@props(['invoice'])

<x-badge :color="App\Models\Invoice::STATUS_COLORS[$invoice->displayStatus()]" {{ $attributes }}>{{ $invoice->displayStatusLabel() }}</x-badge>
