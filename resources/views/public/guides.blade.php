@extends('public.layout')
@push('page-styles')
<style>
  #main:has(.guide-hero){max-width:none;padding:0}
  .guide-hero{background:#f3f9ff;padding:3rem max(1.25rem,calc((100vw - 1120px)/2)) 2rem}
  #main .guide-hero h1{margin:.5rem 0 0;max-width:none;color:#061a3c}
  .guide-eyebrow{font-size:.8rem;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:#008066}
  .guide-jump{display:flex;flex-wrap:wrap;gap:.7rem;margin-top:1.2rem}
  #main .guide-jump a{padding:.6rem 1.2rem;border-radius:999px;text-decoration:none;font-weight:800;background:#e1f4ed;color:#00745a}
  #main .guide-jump a:nth-child(2){background:#e9f1ff;color:#1256b3}
  #main .guide-jump a:nth-child(3){background:#fff0e8;color:#b34511}
  .guide-content{max-width:1120px;margin:auto;padding:1.4rem 1.25rem 4rem}
  #main .guide-content h2{font-size:2rem;margin:2rem 0 0}
  #main .guide-content .section-intro{margin:.1rem 0 1rem;color:#52698f;font-weight:600}
  .guide-search{display:grid;grid-template-columns:1fr 200px auto;gap:.75rem;margin:1rem 0}
  .guide-search input,.guide-search select{width:100%;min-height:48px;border:1px solid #c8dcf2;border-radius:10px;padding:.6rem .9rem;background:white;color:#061a3c}
  .guide-search button{border:0;border-radius:10px;background:#2e6ee5;color:white;padding:.6rem 1.2rem;font-weight:800;cursor:pointer}
  .guide-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.25rem}
  .guide-card{border:1px solid #d4e3f6;border-radius:17px;padding:.65rem;background:white;min-width:0}
  #main .guide-card details{padding:0;border:0}
  #main .guide-card summary{display:flex;align-items:center;justify-content:center;position:relative;aspect-ratio:16/6;border-radius:12px;background:linear-gradient(110deg,#1768be,#48afe6);list-style:none;cursor:pointer}
  #main .guide-card summary::-webkit-details-marker{display:none}
  .guide-card:nth-child(3n+2) summary{background:linear-gradient(110deg,#087c69,#55c6a5)}
  .guide-card:nth-child(3n+3) summary{background:linear-gradient(110deg,#8c45ad,#d784a3)}
  .guide-card.sample:nth-child(3n+1) summary{background:linear-gradient(110deg,#f36b35,#f9b94e)}
  .guide-card.sample:nth-child(3n+2) summary{background:linear-gradient(110deg,#13876e,#a5ce48)}
  .guide-card.sample:nth-child(3n+3) summary{background:linear-gradient(110deg,#2872ba,#78c3d9)}
  .guide-badge{position:absolute;left:12px;top:12px;max-width:calc(100% - 24px);padding:.25rem .7rem;background:white;border-radius:30px;color:#123f74;font-size:.65rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-transform:uppercase}
  .guide-play{display:grid;place-items:center;width:48px;height:48px;border-radius:50%;background:white;color:#008066;font-size:1.1rem}
  .guide-card iframe,.guide-card video{width:100%;aspect-ratio:16/9;border:0;border-radius:10px;margin-top:.6rem;background:#061a3c}
  #main .guide-card h3{font-size:1.1rem;line-height:1.3;margin:.8rem .4rem 0}
  #main .guide-card p{font-size:.85rem;color:#52698f;margin:.25rem .4rem .4rem}
  .guide-count{font-size:.85rem;color:#52698f;font-weight:700;margin:.8rem 0}
  .guide-pager{display:flex;justify-content:flex-end;gap:.3rem;flex-wrap:wrap}
  #main .guide-pager a,#main .guide-pager span{min-width:36px;padding:.4rem .6rem;border:1px solid #d4e3f6;border-radius:8px;text-align:center;text-decoration:none}
  #main .guide-pager [aria-current]{background:#2e6ee5;color:white}
  #main .guide-faq details{border:1px solid #d4e3f6;border-radius:12px;margin:.6rem 0;padding:1rem 1.2rem}
  #main .guide-faq summary{font-weight:800}
  @media(max-width:700px){.guide-search{grid-template-columns:1fr}.guide-search span{display:none}.guide-cards{grid-template-columns:1fr}.guide-hero{padding:2rem 1.25rem}}
</style>
@endpush
@section('content')
<header class="guide-hero"><div class="guide-eyebrow">Learning support</div><h1>Guides to get you started.</h1><p class="intro">Short walkthroughs, language previews and answers for the whole family.</p><nav class="guide-jump" aria-label="Guide sections"><a href="#how-to">How-to videos</a><a href="#samples">Sample videos</a><a href="#faq">FAQ</a></nav></header>
<div class="guide-content">
  <section id="how-to" aria-labelledby="how-heading"><h2 id="how-heading">How-to videos</h2><p class="section-intro">Learn the basics in a few minutes.</p>
    <form class="guide-search" action="/guides#how-to" method="get"><input name="how_search" type="search" maxlength="100" value="{{ $searchHow }}" placeholder="Search how-to videos..." aria-label="Search how-to videos"><select name="topic" aria-label="Filter by topic"><option value="">All topics</option>@foreach(['getting_started'=>'Getting started','parent_guide'=>'Parent guide','learning'=>'Learning','billing'=>'Billing','schools'=>'Schools'] as $code => $name)<option value="{{ $code }}" @selected($topic === $code)>{{ $name }}</option>@endforeach</select><button type="submit">Search</button></form>
    <div class="guide-cards">@forelse($howTo as $video) @include('public.guide-video-card', ['video' => $video]) @empty <p>No how-to videos found.</p> @endforelse</div>
    <p class="guide-count">Showing {{ $howTo->firstItem() ?? 0 }}–{{ $howTo->lastItem() ?? 0 }} of {{ $howTo->total() }} videos</p>@include('public.guide-pagination', ['pages' => $howTo])
  </section>
  <section id="samples" aria-labelledby="sample-heading"><h2 id="sample-heading">Sample videos</h2><p class="section-intro">Hear a little of what learning feels like in each language.</p>
    <form class="guide-search" action="/guides#samples" method="get"><input name="sample_search" type="search" maxlength="100" value="{{ $searchSample }}" placeholder="Search sample videos..." aria-label="Search sample videos"><select name="language" aria-label="Filter by language"><option value="">All languages</option>@foreach(['yoruba'=>'Yorùbá','igbo'=>'Igbo','hausa'=>'Hausa','english'=>'English'] as $code => $name)<option value="{{ $code }}" @selected($language === $code)>{{ $name }}</option>@endforeach</select><button type="submit">Search</button></form>
    <div class="guide-cards">@forelse($samples as $video) @include('public.guide-video-card', ['video' => $video, 'sample' => true]) @empty <p>No sample videos found.</p> @endforelse</div>
    <p class="guide-count">Showing {{ $samples->firstItem() ?? 0 }}–{{ $samples->lastItem() ?? 0 }} of {{ $samples->total() }} videos</p>@include('public.guide-pagination', ['pages' => $samples])
  </section>
  <section id="faq" class="guide-faq" aria-labelledby="faq-heading"><h2 id="faq-heading">Frequently asked questions</h2><p class="section-intro">Quick answers before you begin.</p>
    @forelse($faqs as $faq)<details><summary>{{ $faq->question }}</summary><p>{{ $faq->answer }}</p></details>@empty <p>Questions and answers are coming soon.</p> @endforelse
  </section>
  @if($guides->isNotEmpty())<section aria-labelledby="phrase-heading"><h2 id="phrase-heading">Language phrase guides</h2><div class="grid">@foreach($guides as $guide)<article class="panel"><h3><a href="{{ $guide->path }}">{{ $guide->heading }}</a></h3><p>{{ $guide->description }}</p></article>@endforeach</div></section>@endif
</div>
@endsection
