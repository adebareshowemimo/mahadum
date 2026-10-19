@extends('public.layout')
@push('page-styles')
<link rel="stylesheet" href="/css/languages.css">
@endpush
@section('content')
@php
$cards = [
 'yoruba' => ['name'=>'Yorùbá', 'endonym'=>'Èdè Yorùbá', 'greeting'=>'Ẹ káàrọ̀', 'meaning'=>'Good morning', 'description'=>'Bring everyday words into family conversations, from morning greetings to stories shared across generations.', 'alt'=>'Illustration of a grandmother and granddaughter practising a language lesson on a tablet', 'topics'=>'Greetings · Family · Everyday life'],
 'igbo' => ['name'=>'Igbo', 'endonym'=>'Asụsụ Igbo', 'greeting'=>'Ụtụtụ ọma', 'meaning'=>'Good morning', 'description'=>'Make a little more room for connection. Build confidence with introductions, useful phrases and familiar voices.', 'alt'=>'Illustration of an Igbo father and son using a language-learning app on a tablet', 'topics'=>'Introductions · Stories · Connection'],
 'hausa' => ['name'=>'Hausa', 'endonym'=>'Harshen Hausa', 'greeting'=>'Ina kwana', 'meaning'=>'Good morning', 'description'=>'Start with a warm greeting and keep the conversation growing, one practical word and short lesson at a time.', 'alt'=>'Illustration of a Hausa mother and daughter practising an audio quiz on a tablet', 'topics'=>'Greetings · Everyday words · Conversation'],
 'english' => ['name'=>'English', 'endonym'=>'English', 'greeting'=>'Good morning', 'meaning'=>'Every conversation starts somewhere', 'description'=>'Find your voice for school, home and the wider world. Practise the words that turn learning into everyday confidence.', 'alt'=>'Illustration of a Nigerian teacher and two children using a tablet for a language lesson', 'topics'=>'Speaking · Reading · Everyday confidence'],
];
@endphp
<div class="language-page">
<section class="language-hero" aria-labelledby="language-title"><div class="language-wrap language-hero-inner">
 <div class="language-hero-copy"><p class="language-eyebrow">FOUR LANGUAGES. COUNTLESS CONNECTIONS.</p><h1 id="language-title">A new language.<br>A little closer <span>to your world.</span></h1><p class="language-hero-intro">Learn the language. Live the culture. Connect the generations.</p><p class="language-hero-body">Discover Yorùbá, Igbo, Hausa and English through short lessons, native voices and stories you can bring into everyday life.</p><a class="language-button language-button-accent" href="#choose-language">Find your language <span aria-hidden="true">↗</span></a><p class="language-hero-note">For families, individual learners and schools</p></div>
 <div class="language-hero-art" aria-hidden="true"><img src="/images/languages/yoruba-natural.webp" width="960" height="640" alt="" fetchpriority="high"><div class="language-hello"><span>ONE SMALL GREETING.</span><strong>So many ways to connect.</strong><div><span>Ẹ káàrọ̀</span><span>Ụtụtụ ọma</span><span>Ina kwana</span><span>Good morning</span></div></div></div>
</div></section>
<section class="language-vote" aria-labelledby="language-vote-title">
 <div class="language-wrap language-vote-inner">
  <div class="language-vote-copy">
   <p class="language-eyebrow">YOUR VOICE. OUR NEXT LANGUAGE.</p>
   <h2 id="language-vote-title">Your language<br>could be <span>next.</span></h2>
   <p>Don’t see your language yet? Help decide what comes to Mahadum360 next. Vote for the language you want to learn and bring your community with you.</p>
   <a class="language-button language-vote-button" href="/vote">Vote for the next language <span aria-hidden="true">↗</span></a>
   <p class="language-vote-note">One account. One vote. Verify your email to make it count.</p>
  </div>
  <div class="language-vote-community">
   <span class="language-vote-label">A SHARED GOAL. A NEW CONNECTION.</span>
   <div class="language-vote-goal"><strong>{{ number_format(config('language-votes.target')) }}</strong><span>verified votes for a language<br>to get work started</span></div>
   <div class="language-vote-options" aria-label="Some of the languages you can vote for">
    @foreach(array_slice(config('language-votes.languages'), 0, 6) as $candidate)
    <span>{{ $candidate }}</span>
    @endforeach
    <a href="/vote#language-choices">Find yours <span aria-hidden="true">→</span></a>
   </div>
   <p>Choose a language or suggest another.<br>Help more voices find a home here.</p>
  </div>
 </div>
</section>
<section class="language-choice language-wrap" id="choose-language" aria-labelledby="language-choice-title">
 <div class="language-section-heading"><div><p class="language-eyebrow">YOUR NEXT CHAPTER STARTS HERE</p><h2 id="language-choice-title">Which language feels like you?</h2></div><p>Start with a greeting. <br>Build a habit. Keep the connection growing.</p></div>
 <div class="language-card-grid">
 @foreach($cards as $code => $card)
 <article class="language-card language-card-{{ $code }}" aria-labelledby="language-{{ $code }}">
  <div class="language-card-image"><img src="/images/languages/{{ $code }}-natural.webp" width="960" height="640" alt="{{ $card['alt'] }}" loading="lazy" decoding="async"><span class="language-free">Start free · Level 0</span></div>
  <div class="language-card-content"><div class="language-card-heading"><h3 id="language-{{ $code }}">Learn {{ $card['name'] }}</h3><span>{{ $card['endonym'] }}</span></div><div class="language-greeting"><strong lang="{{ ['yoruba'=>'yo','igbo'=>'ig','hausa'=>'ha','english'=>'en'][$code] }}">{{ $card['greeting'] }}</strong><span>{{ $card['meaning'] }}</span></div><p class="language-card-description">{{ $card['description'] }}</p><p class="language-topics">{{ $card['topics'] }}</p><a class="language-card-link" href="{{ $code === 'english' ? '/register?lang=english' : '/learn-'.$code }}">{{ $code === 'english' ? 'Start English free' : 'Explore '.$card['name'] }} <span aria-hidden="true">↗</span></a></div>
 </article>
 @endforeach
 </div>
</section>
<section class="language-routine language-wrap" aria-labelledby="language-routine-title"><div><p class="language-eyebrow">SMALL STEPS. REAL CONNECTION.</p><h2 id="language-routine-title">Make room for five minutes.</h2><p>A little practice can become part of the way you greet, share and spend time together.</p></div><ol><li><span>01</span><div><h3>Choose your language</h3><p>Follow a familiar voice or discover something new.</p></div></li><li><span>02</span><div><h3>Try Level 0 free</h3><p>Get started with a short lesson and a few everyday words.</p></div></li><li><span>03</span><div><h3>Take it into your day</h3><p>Try a greeting at home, at school or with someone you love.</p></div></li></ol></section>
<section class="language-closing"><div class="language-wrap"><div><p class="language-eyebrow">YOUR FIRST WORD IS JUST THE BEGINNING</p><h2>Let’s start a conversation.</h2><p>Level 0 is free. Paid plans unlock Level 1 and above.</p></div><a class="language-button language-button-accent" href="/register">Start learning free <span aria-hidden="true">↗</span></a><a class="language-plans" href="/pricing">Compare plans →</a></div></section>
</div>
@endsection
