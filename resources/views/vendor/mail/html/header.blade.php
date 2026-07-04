@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (config('brand.logo_url'))
<img src="{{ config('brand.logo_url') }}" class="logo" alt="{{ config('brand.name') }}">
@else
<span class="wordmark">{!! $slot !!}</span>
@endif
</a>
</td>
</tr>
