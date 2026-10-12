@extends('public.layout')
@section('content')
<p>Five minutes a day · Start free with Level 0</p>
<h1>{{ $document->heading }}</h1>
<p class="intro">{{ $document->intro }}</p>
<a class="cta" href="/register?lang={{ $language }}">Start {{ $languages[$language] }} free</a>
<a class="cta secondary" href="https://wa.me/2349028111111?text={{ rawurlencode('Hello Mahadum360, I would like to learn '.$languages[$language].'.') }}">Chat with us on WhatsApp</a>
@include('public.phrases')
<h2>What you will learn</h2>
@if(!empty($content['levels']))
<div class="grid">@foreach($content['levels'] as $level)<article class="panel"><h3>{{ $level['title'] }}</h3><p>{{ $level['description'] }}</p></article>@endforeach</div>
@else
<p>Start with Level 0, then continue with paid lessons from Level 1. Practise greetings, introductions and everyday conversations with native voices.</p>
@endif
@if($document->body)<div class="prose">{{ $document->body }}</div>@endif
@include('public.videos')
<p>Level 0 is free. Paid plans unlock Level 1 and above. <a href="/pricing">Compare plans</a>.</p>
@if(!empty($content['faqs']))
<h2>Frequently asked questions</h2>
@foreach($content['faqs'] as $faq)<details><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>@endforeach
@endif
<h2>Keep learning</h2><nav aria-label="Related language pages">@foreach($languages as $code => $name)@if($code !== $language)<a class="cta secondary" href="/learn-{{ $code }}">Learn {{ $name }}</a>@endif @endforeach<a class="cta secondary" href="/guides">Phrase guides</a></nav>
@if(!empty($content['playlist_url']))<p><a href="{{ $content['playlist_url'] }}">Watch the {{ $languages[$language] }} playlist</a></p>@endif
@endsection
