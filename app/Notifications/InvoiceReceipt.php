<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Concerns\CustomizableMail;
use App\Notifications\Concerns\TagsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * School invoice payment receipt — sent to the organization's contact email
 * when a proforma/seat invoice is settled (PaymentService@settleInvoice).
 * Routed on-demand (Notification::route('mail', ...)) since an org has no
 * single owner user account to notify.
 */
class InvoiceReceipt extends Notification implements ShouldQueue
{
    use CustomizableMail, Queueable, TagsEmail;

    public function __construct(private Invoice $invoice) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->invoice->amount_minor / 100, 2);

        $default = (new MailMessage)
            ->subject('Your school invoice was paid')
            ->greeting('Payment received')
            ->line("₦{$amount} was received for invoice #{$this->invoice->id}.")
            ->line('This message is your receipt.')
            ->action('View invoices', config('brand.url').'/school/invoices');

        $mail = $this->applyOverride('invoice_paid', [
            '{{brand_url}}' => (string) config('brand.url'),
            '{{invoice_id}}' => (string) $this->invoice->id,
            '{{amount}}' => $amount,
        ], $default);

        return $this->tagEmail($mail, 'invoice_paid', $notifiable);
    }
}
