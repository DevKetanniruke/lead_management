<td class="whitespace-nowrap px-4 py-3 text-right">{{ money($client->total_invoiced ?? 0) }}</td>
<td class="whitespace-nowrap px-4 py-3 text-right text-slate-600">{{ money($client->total_paid ?? 0) }}</td>
<td class="whitespace-nowrap px-4 py-3 text-right font-medium {{ ($client->total_outstanding ?? 0) > 0 ? 'text-slate-900' : 'text-slate-400' }}">{{ money($client->total_outstanding ?? 0) }}</td>
