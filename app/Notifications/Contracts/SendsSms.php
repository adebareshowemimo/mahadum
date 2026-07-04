<?php

namespace App\Notifications\Contracts;

/** A notification that can render itself as an SMS / WhatsApp text body. */
interface SendsSms
{
    public function toSms(object $notifiable): string;
}
