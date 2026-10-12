@extends('public.layout')
@section('content')
<h1>Igbo, Yorùbá and Hausa phrase guides</h1>
<p class="intro">Explore everyday words and phrases with native audio and short lessons.</p>
<div class="grid">@forelse($guides as $guide)
<article class="panel"><h2><a href="{{ $guide->path }}">{{ $guide->heading }}</a></h2><p>{{ $guide->description }}</p><p>{{ $guide->author }} · <time datetime="{{ $guide->published_at?->toDateString() }}">{{ $guide->published_at?->format('j F Y') }}</time></p></article>
@empty
<p>Our phrase guides are being prepared. <a href="/languages">Explore the language lessons</a> to start with Level 0.</p>
@endforelse</div>
@endsection
