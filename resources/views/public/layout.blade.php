<!doctype html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    {!! $head !!}
    @foreach ($chrome['styles'] as $style)
        <link rel="stylesheet" href="{{ $style }}">
    @endforeach
    <style>
      body{background:#fff;color:#061a3c;line-height:1.6}
      #main a{color:inherit}
      #main{max-width:1120px;margin:auto;padding:3rem 1.25rem}
      #main h1{font-size:clamp(2.2rem,5vw,3.8rem);line-height:1.12;max-width:22ch}
      #main h2{font-size:1.7rem;margin-top:2.5rem}
      #main .intro{max-width:70ch;font-size:1.2rem}
      #main .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,270px),1fr));gap:1.25rem}
      #main .panel{border:1px solid #c8dcf2;border-radius:16px;padding:1.3rem;background:#f6faff}
      #main .cta{display:inline-block;padding:.8rem 1.2rem;margin:.5rem .5rem .5rem 0;border-radius:12px;background:#0757bd;color:white;font-weight:800;text-decoration:none}
      #main .secondary{background:#eff7ff;color:#0757bd}
      #main table{width:100%;border-collapse:collapse}
      #main th,#main td{text-align:left;padding:1rem;border-bottom:1px solid #d8e6f5}
      #main audio{max-width:100%;width:240px}
      #main details{padding:1rem 0;border-bottom:1px solid #d8e6f5}
      #main summary{font-weight:800;cursor:pointer}
      #main progress{width:100%;height:1rem}
      #main button.video{border:0;border-radius:12px;background:#061a3c;color:white;width:100%;aspect-ratio:16/9;cursor:pointer;font:inherit;font-weight:800}
      #main .video iframe{width:100%;aspect-ratio:16/9;border:0}
      .skip{position:absolute;left:-9999px}
      .skip:focus{left:1rem;top:1rem;background:white;padding:1rem;z-index:50}
      #main .prose{max-width:75ch;white-space:pre-line}
      @media(max-width:600px){#main .phrase-table{overflow:auto}
      #main td,#main th{padding:.7rem}
      }
      #main h1{margin-bottom:2rem}
      #main h2{margin-bottom:1rem}
      #main p{margin:1rem 0}
      #main a{text-decoration:underline}
      #main .cta{text-decoration:none}
    </style>
    <script src="/public-pages.js" defer></script>
    <script type="module" src="{{ $chrome['script'] }}"></script>
</head>
<body class="landing-page">
<a class="skip" href="#main">Skip to main content</a>
<div id="website-header" class="contents">{!! $chrome['header'] !!}</div>
<main id="main">@yield('content')</main>
<div id="website-footer" class="contents">{!! $chrome['footer'] !!}</div>
</body></html>
