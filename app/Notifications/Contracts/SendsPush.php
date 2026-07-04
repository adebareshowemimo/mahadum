<?php

namespace App\Notifications\Contracts;

/** A notification that can render itself as a push payload. */
interface SendsPush
{
    /**
     * @return array{title: string, body: string, data?: array<string, mixed>}
     */
    public function toPush(object $notifiable): array;
}
