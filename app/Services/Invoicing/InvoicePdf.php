<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Facades\Storage;

/** Builds the branded invoice document; the same view also powers the printable HTML version. */
class InvoicePdf
{
    public function make(Invoice $invoice): DomPdf
    {
        $pdf = Pdf::loadView('invoices.document', $this->data($invoice, forPdf: true))
            ->setPaper('a4')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isFontSubsettingEnabled' => true]);

        // Page numbers drawn on the canvas (avoids enabling inline PHP in templates).
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 165, $canvas->get_height() - 26,
            $invoice->number.'  ·  Page {PAGE_NUM} of {PAGE_COUNT}',
            $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 7.5, [0.45, 0.5, 0.6],
        );

        return $pdf;
    }

    public function filename(Invoice $invoice): string
    {
        return 'Invoice-'.preg_replace('/[^A-Za-z0-9\-_]/', '-', $invoice->number).'.pdf';
    }

    /** @return array<string, mixed> */
    public function data(Invoice $invoice, bool $forPdf = false): array
    {
        $invoice->loadMissing('items', 'client');
        $seller = $invoice->seller();

        return [
            'invoice' => $invoice,
            'seller' => $seller,
            'billing' => $invoice->billing(),
            'logo' => $this->imageDataUri($seller['logo_path'] ?? null),
            'signature' => $this->imageDataUri($seller['signature_path'] ?? null),
            'forPdf' => $forPdf,
        ];
    }

    /** Embed stored images inline so the PDF needs no remote or file-system access at render time. */
    private function imageDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($path) ?: 'image/png';
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($path));
    }
}
