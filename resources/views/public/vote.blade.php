@extends('public.layout')
@section('content')
<h1>Vote for the next Nigerian language</h1>
<p class="intro">One sign-up = one vote. Choose the language you would like us to add when you create your account.</p>
<p>At {{ number_format($target) }} sign-ups for a language, work starts, with a target of being ready in 60 days.</p>
<a class="cta" href="/register">Sign up and vote</a>
<div class="grid">@foreach($languages as $language)
<article class="panel"><h2>{{ $language }}</h2><p>{{ number_format($counts[$language] ?? 0) }} of {{ number_format($target) }} votes</p><progress value="{{ min($target, $counts[$language] ?? 0) }}" max="{{ $target }}" aria-label="{{ $language }} votes"></progress></article>
@endforeach</div>
@endsection
