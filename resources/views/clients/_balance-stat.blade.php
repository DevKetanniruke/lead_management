@php $overdue = $client->invoices->filter->isOverdue(); @endphp
<x-stat label="Outstanding" :value="money($client->invoices->filter->isIssued()->sum('balance_due'))"
        :sub="money($client->invoices->filter->isIssued()->sum('grand_total')).' invoiced'.($overdue->count() ? ' · '.$overdue->count().' overdue' : '')"
        icon="invoice" :alert="$overdue->isNotEmpty()" tone="bg-amber-50 text-amber-600" />
