@extends('public.layout')
@section('content')
<p>For families at home and abroad, and schools</p>
<h1>Learn Nigerian languages together</h1>
<p class="intro">Choose your language and start with five-minute lessons, native voices and stories. Level 0 is free; paid plans unlock Level 1 and above.</p>
<div class="grid">
@foreach($languages as $code => $name)
<article class="panel"><h2>Learn {{ $name }}</h2><p>Build confidence with greetings, introductions and everyday words.</p><a class="cta" href="/learn-{{ $code }}">Explore {{ $name }}</a></article>
@endforeach
</div>
@endsection
