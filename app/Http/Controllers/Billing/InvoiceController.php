<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\PayInvoiceRequest;
use App\Models\Organization;
use App\Services\Billing\InvoicePdfRenderer;
use App\Services\Billing\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private PaymentGatewayManager $gateways) {}

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

    /**
     * Start a gateway checkout for an unpaid invoice. The invoice is marked
     * `paid` by the gateway WEBHOOK (correlated via the `invoice_<id>`
     * reference), never by the client — same pattern as wallet funding and
     * card subscriptions.
     */
    public function pay(PayInvoiceRequest $request, Organization $organization, string $invoice): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);

        $invoiceModel = $organization->invoices()->findOrFail($invoice);
        abort_unless($invoiceModel->status === 'unpaid', 422, 'This invoice is not payable.');

        $reference = 'invoice_'.$invoiceModel->id;
        $checkout = $this->gateways->driver($request->string('gateway')->value() ?: null)->initialize(
            $reference,
            $invoiceModel->amount_minor,
            (string) $organization->contact_email,
            ['purpose' => 'invoice', 'invoice_id' => $invoiceModel->id],
        );

        // Record the gateway's own transaction id when it returns one, so a later
        // refund that doesn't echo our `invoice_<id>` reference (e.g. Monnify) correlates.
        if ($checkout->providerReference !== null) {
            $invoiceModel->update(['gateway_txn_ref' => $checkout->providerReference]);
        }

        return response()->json(['data' => [
            'invoice_id' => $invoiceModel->id,
            'payment_reference' => $reference,
            'checkout_url' => $checkout->checkoutUrl,
        ]]);
    }
}
