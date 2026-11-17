<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ApplyInvoicePromoRequest;
use App\Http\Requests\Billing\PayInvoiceRequest;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Services\AuditLogger;
use App\Services\Billing\InvoiceLineBuilder;
use App\Services\Billing\InvoicePdfRenderer;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\Billing\PaymentService;
use App\Services\Billing\PromoException;
use App\Services\Billing\PromoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private PaymentGatewayManager $gateways) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);

        $invoices = $organization->invoices()->where('status', '!=', 'cancelled')->latest()->get()->map(fn ($i) => [
            'id' => $i->id,
            'type' => $i->type,
            'amount_minor' => $i->amount_minor,
            'lines' => $i->breakdownLines(),
            'status' => $i->status,
            'payment_gateway' => $i->payment_gateway,
            'checkout_started' => $i->gateway_txn_ref !== null,
            'checkout_url' => $i->checkout_url,
            'issued_at' => $i->issued_at,
            'paid_at' => $i->paid_at,
            'has_pdf' => $i->pdf_asset_id !== null,
        ]);

        return response()->json(['data' => $invoices]);
    }

    public function gateways(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);
        $available = $this->gateways->available();
        $preferred = (string) config('services.payments.default', 'monnify');

        return response()->json(['data' => [
            'available' => $available,
            'default' => in_array($preferred, $available, true) ? $preferred : ($available[0] ?? null),
        ]]);
    }

    /** Verify a returning checkout before showing an invoice as paid. */
    public function confirm(Request $request, Organization $organization, string $invoice, PaymentService $payments): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);
        $model = $organization->invoices()->findOrFail($invoice);
        if ($model->status === 'paid' || $model->status === 'cancelled' || $model->gateway_txn_ref === null) {
            return response()->json(['data' => $this->confirmationPayload($model)]);
        }

        $provider = $model->payment_gateway;
        abort_unless(in_array($provider, $this->gateways->available(), true), 503, 'This payment provider is unavailable. Check again later.');
        $reference = 'invoice_'.$model->id;
        $verified = $this->gateways->driver($provider)->verify($reference);
        if ($verified->status === 'success') {
            $details = $provider === 'monnify' ? ($verified->raw['responseBody'] ?? []) : ($verified->raw['data'] ?? []);
            $returnedReference = $provider === 'monnify' ? ($details['paymentReference'] ?? null)
                : ($details[$provider === 'flutterwave' ? 'tx_ref' : 'reference'] ?? null);
            $currency = $provider === 'monnify' ? ($details['currencyCode'] ?? $details['currency'] ?? null) : ($details['currency'] ?? null);
            if ($returnedReference !== $reference || $currency !== 'NGN' || $verified->amountMinor !== $model->amount_minor) {
                return response()->json(['data' => $this->confirmationPayload($model, 'needs_review')]);
            }
            $payments->process($provider, 'invoice_verify_'.$model->id, $reference, 'success', $verified->amountMinor, $verified->raw);
        }

        return response()->json(['data' => $this->confirmationPayload($model->fresh(), $verified->status)]);
    }

    /** @return array<string, mixed> */
    private function confirmationPayload(Invoice $invoice, string $verification = 'pending'): array
    {
        return [
            'id' => $invoice->id, 'status' => $invoice->status,
            'verification' => $invoice->status === 'paid' ? 'success' : $verification,
            'amount_minor' => $invoice->amount_minor,
            'payment_gateway' => $invoice->payment_gateway,
            'payment_reference' => 'invoice_'.$invoice->id,
            'issued_at' => $invoice->issued_at, 'paid_at' => $invoice->paid_at,
        ];
    }

    /** Apply a validated discount once, before checkout. */
    public function applyPromo(ApplyInvoicePromoRequest $request, Organization $organization, string $invoice, PromoService $promos): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);

        return DB::transaction(function () use ($request, $organization, $invoice, $promos) {
            $model = $organization->invoices()->lockForUpdate()->findOrFail($invoice);
            abort_unless($model->status === 'unpaid' && $model->gateway_txn_ref === null, 422, 'This invoice cannot be discounted after checkout has started.');
            $lines = $model->breakdownLines();
            abort_if(collect($lines)->contains(fn ($line) => str_starts_with($line['description'], 'Promo code:')), 422, 'A promo code has already been applied.');
            // Serialize competing invoice redemptions against the global cap.
            PromoCode::whereRaw('LOWER(code) = ?', [mb_strtolower(trim($request->string('code')->value()))])->lockForUpdate()->first();
            try {
                $outcome = $promos->evaluateInvoice($request->string('code')->value(), $model, $request->user());
            } catch (PromoException $exception) {
                throw ValidationException::withMessages(['code' => $exception->getMessage()]);
            }
            $targetLabel = match ($outcome->promo->target ?? 'all') {
                'school_registration' => 'School registration',
                'school_subscription' => 'School subscription',
                default => 'All fees',
            };
            $lines[] = ['description' => 'Promo code: '.$outcome->promo->code.' ('.$targetLabel.')', 'amount_minor' => -$outcome->discountMinor];
            $total = $model->amount_minor - $outcome->discountMinor;
            if (($outcome->promo->target ?? 'all') !== 'all' && collect($lines)->contains('description', 'VAT (7.5%)')) {
                // Use the existing invoice tax rule on the discounted subtotal.
                $fees = array_values(array_filter($lines, fn ($line) => $line['description'] !== 'VAT (7.5%)'));
                $breakdown = InvoiceLineBuilder::withVat($fees);
                $lines = $breakdown['lines'];
                $total = $breakdown['total_minor'];
            }
            $before = ['amount_minor' => $model->amount_minor];
            $model->update(['amount_minor' => $total, 'lines' => $lines, 'pdf_asset_id' => null]);
            PromoRedemption::create(['promo_code_id' => $outcome->promo->id, 'user_id' => $request->user()->id, 'organization_id' => $organization->id]);
            $outcome->promo->increment('redeemed_count');
            app(AuditLogger::class)->record('invoice.promo_applied', $model, $before, ['amount_minor' => $model->amount_minor, 'code' => $outcome->promo->code, 'target' => $outcome->promo->target, 'discount_minor' => $outcome->discountMinor], $organization->id);

            return response()->json(['data' => ['amount_minor' => $model->amount_minor]]);
        });
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

        return DB::transaction(function () use ($request, $organization, $invoice) {
            $invoiceModel = $organization->invoices()->lockForUpdate()->findOrFail($invoice);
            abort_unless($invoiceModel->status === 'unpaid', 422, 'This invoice is not payable.');
            abort_unless($invoiceModel->gateway_txn_ref === null, 409,
                'A checkout has already started for this invoice. Check its payment status before trying again.');

            if ($invoiceModel->amount_minor === 0) {
                app(PaymentService::class)->settleZeroInvoice($invoiceModel);

                return response()->json(['data' => ['invoice_id' => $invoiceModel->id, 'payment_reference' => 'invoice_'.$invoiceModel->id, 'checkout_url' => null, 'settled' => true]]);
            }

            $gateway = $request->string('gateway')->value() ?: (string) config('services.payments.default', 'monnify');
            abort_unless(in_array($gateway, $this->gateways->available(), true), 422, 'This payment provider is currently unavailable. Please try again later.');

            $reference = 'invoice_'.$invoiceModel->id;
            $checkout = $this->gateways->driver($gateway)->initialize(
                $reference,
                $invoiceModel->amount_minor,
                (string) $organization->contact_email,
                ['purpose' => 'invoice', 'invoice_id' => $invoiceModel->id, 'organization_id' => $organization->id],
            );
            abort_unless(filled($checkout->checkoutUrl), 502, 'The payment gateway did not return a checkout URL.');

            // Record the gateway's own transaction id when it returns one, so a later
            // refund that doesn't echo our `invoice_<id>` reference (e.g. Monnify) correlates.
            $invoiceModel->update(['gateway_txn_ref' => $checkout->providerReference ?? $reference,
                'payment_gateway' => $gateway, 'checkout_url' => $checkout->checkoutUrl]);

            return response()->json(['data' => [
                'invoice_id' => $invoiceModel->id,
                'payment_reference' => $reference,
                'payment_gateway' => $gateway,
                'checkout_url' => $checkout->checkoutUrl,
            ]]);
        });
    }
}
