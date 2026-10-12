@extends('public.layout')
@section('content')
<p><a href="/guides">All phrase guides</a></p><h1>{{ $document->heading }}</h1>
<p>{{ $document->author }} · <time datetime="{{ $document->published_at?->toDateString() }}">{{ $document->published_at?->format('j F Y') }}</time></p>
<p class="intro">{{ $document->intro }}</p><div class="prose">{{ $document->body }}</div>
@include('public.phrases')
@include('public.videos')
@if(!empty($content['language']))<p><a class="cta" href="/learn-{{ $content['language'] }}">Continue with the language lessons</a></p>@endif
@endsection
