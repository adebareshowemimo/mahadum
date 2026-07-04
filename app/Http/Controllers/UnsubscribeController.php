<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\EmailSuppression;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One-click unsubscribe for marketing email. The link is a signed URL (no login),
 * so clicking it is proof enough; it adds the address to the global suppression
 * list and marks any matching contacts unsubscribed. Idempotent.
 */
class UnsubscribeController extends Controller
{
    public function __invoke(Request $request, string $email): View
    {
        $email = mb_strtolower(trim($email));

        EmailSuppression::firstOrCreate(['email' => $email], ['reason' => 'unsubscribe']);

        Contact::where('email', $email)->whereNull('unsubscribed_at')->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        return view('emails.unsubscribed', ['email' => $email]);
    }
}
