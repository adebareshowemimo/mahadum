<x-mail::message>
{!! $bodyHtml !!}

<x-slot:subcopy>
You’re receiving this because you’re on a {{ config('brand.name') }} mailing list.
[Unsubscribe]({{ $unsubscribeUrl }}) at any time.
</x-slot:subcopy>
</x-mail::message>
