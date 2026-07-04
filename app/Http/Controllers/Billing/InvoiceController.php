<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Billing\InvoicePdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);

        $invoices = $organization->invoices()->latest()->get()->map(fn ($i) => [
            'id' => $i->id,
            'type' => $i->type,
            'amount_minor' => $i->amount_minor,
            'status' => $i->status,
            'issued_at' => $i->issued_at,
            'paid_at' => $i->paid_at,
            'has_pdf' => $i->pdf_asset_id !== null,
        ]);

        return response()->json(['data' => $invoices]);
    }

    /** Generate (once) and stream the invoice PDF for download. */
    public function download(Request $request, Organization $organization, string $invoice, InvoicePdfRenderer $renderer): StreamedResponse
    {
        $this->authorizeOrg($request->user(), $organization);

        $invoiceModel = $organization->invoices()->findOrFail($invoice);
        $asset = $renderer->render($invoiceModel);

        return Storage::disk($renderer->disk())->download($asset->url, "invoice-{$invoiceModel->id}.pdf");
    }
}
