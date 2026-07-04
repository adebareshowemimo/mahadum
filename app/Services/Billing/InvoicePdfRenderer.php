<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\MediaAsset;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Renders an invoice to a PDF and persists it as a private MediaAsset (on the
 * local disk). Idempotent — returns the existing asset if the invoice has
 * already been rendered, so re-downloads don't regenerate.
 */
class InvoicePdfRenderer
{
    private const DISK = 'local';

    public function render(Invoice $invoice): MediaAsset
    {
        if ($invoice->pdf_asset_id !== null && ($existing = $invoice->pdfAsset) !== null) {
            return $existing;
        }

        $invoice->loadMissing('organization');

        $pdf = Pdf::loadView('invoices.pdf', ['invoice' => $invoice]);
        $path = "invoices/invoice-{$invoice->id}.pdf";
        Storage::disk(self::DISK)->put($path, $pdf->output());

        $asset = MediaAsset::create(['type' => 'pdf', 'url' => $path]);
        $invoice->update(['pdf_asset_id' => $asset->id]);

        return $asset;
    }

    public function disk(): string
    {
        return self::DISK;
    }
}
