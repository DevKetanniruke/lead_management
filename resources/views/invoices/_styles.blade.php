{{-- Scoped to .inv-doc so it can sit inside the app UI as well as in the PDF/print documents. Table-based for dompdf. --}}
<style>
    .inv-doc { font-family: "DejaVu Sans", Inter, Arial, sans-serif; font-size: 10px; color: #1e293b; line-height: 1.4; }
    .inv-doc table { width: 100%; border-collapse: collapse; }
    .inv-doc td, .inv-doc th { vertical-align: top; padding: 0; }
    .inv-doc .muted { color: #64748b; }
    .inv-doc .right { text-align: right; }
    .inv-doc .center { text-align: center; }
    .inv-doc .nowrap { white-space: nowrap; }
    .inv-doc .brand { font-size: 17px; font-weight: bold; color: #0f172a; }
    .inv-doc .title { font-size: 22px; font-weight: bold; color: #4338ca; letter-spacing: 1px; }
    .inv-doc .logo { max-height: 60px; max-width: 200px; margin-bottom: 6px; }
    .inv-doc .meta td { padding: 1px 0; }
    .inv-doc .meta td.label { color: #64748b; padding-right: 10px; }
    .inv-doc .section { margin-top: 14px; }
    .inv-doc .box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; }
    .inv-doc .box-title { font-size: 9px; text-transform: uppercase; letter-spacing: .8px; color: #64748b; font-weight: bold; margin-bottom: 4px; }
    .inv-doc .items { margin-top: 14px; }
    .inv-doc .items thead { display: table-header-group; }
    .inv-doc .items tr { page-break-inside: avoid; }
    .inv-doc .items th { background: #eef2ff; color: #3730a3; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; padding: 6px 6px; text-align: left; border-bottom: 1px solid #c7d2fe; }
    .inv-doc .items td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; }
    .inv-doc .items th.right, .inv-doc .items td.right { text-align: right; }
    .inv-doc .totals { width: 46%; margin-left: 54%; margin-top: 12px; page-break-inside: avoid; }
    .inv-doc .totals td { padding: 3px 6px; }
    .inv-doc .totals .grand td { border-top: 2px solid #4338ca; font-size: 13px; font-weight: bold; color: #0f172a; padding-top: 7px; }
    .inv-doc .totals .due td { background: #eef2ff; font-weight: bold; color: #3730a3; }
    .inv-doc .words { margin-top: 8px; font-style: italic; color: #475569; }
    .inv-doc .stamp { display: inline-block; border: 2px solid; border-radius: 4px; padding: 2px 10px; font-size: 13px; font-weight: bold; letter-spacing: 2px; margin-top: 6px; }
    .inv-doc .stamp-paid { color: #059669; border-color: #059669; }
    .inv-doc .stamp-void { color: #dc2626; border-color: #dc2626; }
    .inv-doc .stamp-draft { color: #64748b; border-color: #94a3b8; }
    .inv-doc .stamp-overdue { color: #dc2626; border-color: #dc2626; }
    .inv-doc .footer-block { margin-top: 14px; }
    .inv-doc .signature { margin-top: 18px; text-align: right; page-break-inside: avoid; }
    .inv-doc .signature img { max-height: 50px; max-width: 180px; }
    .inv-doc .signature .line { display: inline-block; border-top: 1px solid #94a3b8; padding-top: 4px; min-width: 180px; text-align: center; }
    .inv-doc .pre { white-space: pre-line; }
    .inv-doc .thanks { margin-top: 20px; text-align: center; color: #64748b; font-size: 9.5px; }
</style>
