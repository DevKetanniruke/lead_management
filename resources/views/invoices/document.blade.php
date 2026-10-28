<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    @include('invoices._styles')
    <style>
        @page { margin: 28px 32px 40px; }
        body { margin: 0; }
    </style>
</head>
<body>
    @include('invoices._document')
</body>
</html>
