{{-- Accessible table view of a chart's data. --}}
<details class="mt-3 text-sm">
    <summary class="cursor-pointer select-none text-xs font-medium text-slate-500 hover:text-slate-700">View as table</summary>
    <div class="mt-2 max-h-64 overflow-auto rounded-lg border border-slate-200">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="sticky top-0 bg-slate-50 text-left text-xs font-medium text-slate-500">
                <tr>
                    <th class="w-12 px-3 py-2 text-slate-500">S.No</th>
                    @foreach ($headers as $i => $h)<th class="px-3 py-2 {{ $i ? 'text-right' : '' }}">{{ $h }}</th>@endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($rows as $row)
                    <tr>
                        <td class="whitespace-nowrap px-3 py-1.5 text-xs text-slate-500">{{ $loop->iteration }}</td>
                        @foreach ($row as $i => $cell)<td class="px-3 py-1.5 {{ $i ? 'text-right' : '' }}">{{ $cell }}</td>@endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</details>
