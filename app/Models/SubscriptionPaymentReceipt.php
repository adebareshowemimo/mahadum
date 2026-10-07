<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Confirmed, idempotent subscription payment/refund amounts; no price estimates. */
class SubscriptionPaymentReceipt extends Model
{
    protected $guarded = [];
}
