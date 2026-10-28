<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->number }}</title>
    @include('invoices._styles')
    <style>
        body { margin: 0; background: #f1f5f9; font-family: Inter, Arial, sans-serif; }
        .toolbar { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 12px; background: #0f172a; }
        .toolbar a, .toolbar button { font: 500 13px/1 Inter, Arial, sans-serif; padding: 9px 14px; border-radius: 8px; border: 0; cursor: pointer; text-decoration: none; }
        .toolbar .primary { background: #4f46e5; color: #fff; }
        .toolbar .ghost { background: #1e293b; color: #e2e8f0; }
        .sheet { box-sizing: border-box; width: 210mm; max-width: 100%; min-height: 297mm; margin: 24px auto; padding: 18mm 16mm; background: #fff; box-shadow: 0 10px 30px rgba(15, 23, 42, .12); }
        .inv-doc { font-size: 12px; }
        @page { size: A4; margin: 12mm; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()">Print</button>
        <a class="ghost" href="{{ route('invoices.pdf', $invoice) }}">Download PDF</a>
        <a class="ghost" href="{{ route('invoices.show', $invoice) }}">Back to invoice</a>
    </div>
    <div class="sheet">
        @include('invoices._document')
    </div>
    @if (request()->boolean('autoprint'))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
