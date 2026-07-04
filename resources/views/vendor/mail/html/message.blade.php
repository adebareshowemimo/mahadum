<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('brand.url')">
{{ config('brand.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ config('brand.tagline') }}

© {{ date('Y') }} {{ config('brand.name') }} · {{ config('brand.address') }}
Questions? [{{ config('brand.support_email') }}](mailto:{{ config('brand.support_email') }})
@isset($unsubscribeUrl)

[Unsubscribe]({{ $unsubscribeUrl }}) from these emails.
@endisset
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
