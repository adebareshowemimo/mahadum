@extends('public.layout')
@push('page-styles')
<link rel="stylesheet" href="/vote.css">
@endpush
@section('content')
<div class="vote-page">
    <section class="vote-hero" aria-labelledby="vote-title">
        <div class="vote-wrap vote-hero-inner">
            <div class="vote-hero-copy">
                <p class="vote-eyebrow">OUR LANGUAGES. OUR NEXT CHAPTER.</p>
                <h1 id="vote-title">Your voice.<br>The next <span>language.</span></h1>
                <p class="vote-hero-intro">Help more people learn the language of home.</p>
                <p>Which Nigerian language would you love to learn on Mahadum360? Choose your favourite and help your community take the next step.</p>
                <a class="vote-primary" href="#language-choices">Find your language <span aria-hidden="true">↓</span></a>
                <p class="vote-hero-note">One account. One vote. Every verified voice counts.</p>
            </div>
            <aside class="vote-hero-visual" aria-label="Community voting goal">
                <img src="/images/languages/english-natural.webp" alt="A teacher and children using the learning app together on a tablet" width="960" height="640" fetchpriority="high">
                <div class="vote-goal"><div><span class="vote-eyebrow">THE GOAL FOR EACH LANGUAGE</span><strong>{{ number_format($target) }}</strong><span>verified votes to get work started</span></div><p>Reach the goal and work begins, with a target of being ready in 60 days.</p></div>
            </aside>
        </div>
    </section>

    <div class="vote-wrap">
        <ol class="vote-steps" aria-label="How voting works">
            <li><span class="vote-step-number">01</span><div><strong>Find your language</strong><p>Explore the shortlist or suggest another.</p></div></li>
            <li><span class="vote-step-number">02</span><div><strong>Make your choice</strong><p>Review it before confirming your one vote.</p></div></li>
            <li><span class="vote-step-number">03</span><div><strong>Verify your email</strong><p>Your vote counts once your email is verified.</p></div></li>
        </ol>

        <section id="language-choices" class="vote-choices" aria-labelledby="choices-title">
            <div class="vote-section-heading"><div><p class="vote-eyebrow">A LANGUAGE. A CONNECTION. YOUR CHOICE.</p><h2 id="choices-title">Which language is yours?</h2><p>Choose the language you want us to add next.</p></div><div class="vote-total"><strong id="vote-total-count">{{ number_format($counts->sum()) }}</strong><span>verified community votes</span></div></div>
            <div class="vote-toolbar">
                <div class="vote-search"><label for="language-search">Find a language</label><div><span aria-hidden="true">⌕</span><input id="language-search" type="search" placeholder="Search the shortlist…" autocomplete="off"><button id="vote-clear" type="button" hidden>Clear</button></div></div>
                <div class="vote-sort"><label for="vote-sort">Sort languages</label><select id="vote-sort"><option value="name">A to Z</option><option value="votes">Most votes</option></select></div>
            </div>
            <p id="vote-results" class="vote-results" role="status" aria-live="polite">{{ count(array_filter($languages, fn ($language) => $language !== 'Other')) }} languages to choose from</p>
            <div id="vote-feedback" class="vote-feedback" role="status" aria-live="polite" hidden><p id="vote-feedback-text"></p><a id="vote-feedback-link" href="/login" hidden>Sign in to your account →</a></div>
            <div class="vote-grid" id="vote-grid">
                @foreach($languages as $language)
                @continue($language === 'Other')
                @php($count = $counts[$language] ?? 0)
                <article class="vote-card" data-language="{{ $language }}" data-count="{{ $count }}" data-target="{{ $target }}">
                    <div class="vote-card-top"><span class="vote-monogram" aria-hidden="true">{{ mb_substr($language, 0, 2) }}</span><span class="vote-percent">{{ round(min(100, $count / max(1, $target) * 100), 1) }}% of goal</span></div>
                    <h3>{{ $language }}</h3>
                    <div class="vote-count">{{ number_format($count) }} of {{ number_format($target) }} votes</div>
                    <progress value="{{ min($target, $count) }}" max="{{ $target }}" aria-label="{{ $language }} verified votes"></progress>
                    <button type="button" class="vote-button" data-vote="{{ $language }}" aria-pressed="false">Choose {{ $language }} <span aria-hidden="true">↗</span></button>
                </article>
                @endforeach
            </div>
            <div id="vote-empty" class="vote-empty" hidden><h3>No matches yet.</h3><p>Try another spelling or suggest your language below.</p><button type="button" id="vote-reset">Show all languages</button></div>
        </section>

        <section class="vote-suggest" aria-labelledby="vote-suggest-title"><div><p class="vote-eyebrow">THERE’S ROOM FOR MORE VOICES</p><h2 id="vote-suggest-title">Don’t see your language?</h2><p>Tell us what you’d love to learn. Suggestions help shape what comes next.</p></div><div><label for="other-language">Your language</label><div class="vote-suggest-input"><input id="other-language" maxlength="60" placeholder="Enter a language name" autocomplete="off"><button type="button" class="vote-primary" data-vote="Other">Suggest a language <span aria-hidden="true">↗</span></button></div><p>Suggestions are reviewed by the Mahadum360 team.</p></div></section>

        <section class="vote-faq" aria-labelledby="vote-faq-title"><div><p class="vote-eyebrow">A LITTLE CLARITY BEFORE YOU VOTE</p><h2 id="vote-faq-title">Every voice starts with trust.</h2><p>Already learning with us?<br><a href="/login">Sign in</a> and return here to vote.</p><a class="vote-back" href="/languages">Explore our current languages →</a></div><div>
            <details><summary>Who can vote?</summary><p>Anyone with a Mahadum360 account and a verified email address. Browse the languages without signing in, then create an account or sign in to make your choice count. Each account gets one vote.</p></details>
            <details><summary>I chose a language when I signed up. Does it count?</summary><p>Your choice is saved. It counts automatically once you verify your email. You do not need to vote again.</p></details>
            <details><summary>Can I change my vote?</summary><p>Each account gets one language vote. Review your choice before confirming; a recorded vote cannot be changed here.</p></details>
            <details><summary>What happens when a language reaches the goal?</summary><p>At {{ number_format($target) }} verified votes for a language, work starts, with a target of being ready in 60 days. Suggestions for other languages are reviewed by the team.</p></details>
        </div></section>
    </div>

    <dialog id="vote-dialog" aria-labelledby="vote-dialog-title" aria-describedby="vote-dialog-description">
        <button type="button" id="vote-dialog-close" class="vote-dialog-close" aria-label="Close vote confirmation">×</button>
        <p class="vote-eyebrow">ONE VOICE. ONE CHOICE.</p><h2 id="vote-dialog-title">Your choice: <span id="vote-selected-name"></span></h2>
        <p id="vote-dialog-description">Each account gets one vote. Check your choice before continuing.</p>
        <p id="vote-dialog-message" role="status" aria-live="polite"></p>
        <div class="vote-dialog-actions"><button type="button" id="vote-confirm" class="vote-primary">Confirm my vote <span aria-hidden="true">→</span></button><button type="button" id="vote-dialog-back">Choose another language</button></div>
        <p id="vote-dialog-auth" hidden>Already have an account? <a href="/login">Sign in</a>, then return here to vote.</p>
    </dialog>
</div>
<script src="/vote.js" defer></script>
@endsection
