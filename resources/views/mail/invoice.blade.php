<x-mail::message>
# Invoice {{ $invoice->number }}

Hello {{ $invoice->billing()['name'] ?? 'there' }},

@if ($note)
{{ $note }}

@endif
Please find attached invoice **{{ $invoice->number }}** dated {{ $invoice->invoice_date->format('M j, Y') }}.

<x-mail::table>
| | |
|:--|--:|
| Invoice total | {{ money($invoice->grand_total, $invoice->currency) }} |
| Amount paid | {{ money($invoice->amount_paid, $invoice->currency) }} |
| **Balance due** | **{{ money($invoice->balance_due, $invoice->currency) }}** |
| Due date | {{ $invoice->due_date->format('M j, Y') }} |
</x-mail::table>

@if (! empty($seller['payment_instructions']))
**How to pay**

{{ $seller['payment_instructions'] }}
@endif

Thank you,<br>
{{ $seller['name'] }}
</x-mail::message>
