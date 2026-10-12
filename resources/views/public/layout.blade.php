<!doctype html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    {!! $head !!}
    <style>
      body{margin:0;background:#fff;color:#061a3c;font-family:Nunito,system-ui,sans-serif;line-height:1.6}a{color:inherit}header,footer{padding:1.2rem max(1.2rem,calc((100vw - 1120px)/2));background:#f1f8ff}header nav,footer nav{display:flex;gap:1rem;flex-wrap:wrap;align-items:center}header img{height:48px;width:auto}main{max-width:1120px;margin:auto;padding:3rem 1.25rem}h1{font-size:clamp(2.2rem,5vw,3.8rem);line-height:1.12;max-width:22ch}h2{font-size:1.7rem;margin-top:2.5rem}.intro{max-width:70ch;font-size:1.2rem}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,270px),1fr));gap:1.25rem}.panel{border:1px solid #c8dcf2;border-radius:16px;padding:1.3rem;background:#f6faff}.cta{display:inline-block;padding:.8rem 1.2rem;margin:.5rem .5rem .5rem 0;border-radius:12px;background:#0757bd;color:white;font-weight:800;text-decoration:none}.secondary{background:#eff7ff;color:#0757bd}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:1rem;border-bottom:1px solid #d8e6f5}audio{max-width:100%;width:240px}details{padding:1rem 0;border-bottom:1px solid #d8e6f5}summary{font-weight:800;cursor:pointer}progress{width:100%;height:1rem}button.video{border:0;border-radius:12px;background:#061a3c;color:white;width:100%;aspect-ratio:16/9;cursor:pointer;font:inherit;font-weight:800}.video iframe{width:100%;aspect-ratio:16/9;border:0}.skip{position:absolute;left:-9999px}.skip:focus{left:1rem;top:1rem;background:white;padding:1rem;z-index:10}.prose{max-width:75ch;white-space:pre-line}@media(max-width:600px){.phrase-table{overflow:auto}td,th{padding:.7rem}header img{height:36px}}
    </style>
    <script src="/public-pages.js" defer></script>
</head>
<body>
<a class="skip" href="#main">Skip to main content</a>
<header><nav aria-label="Primary">
    <a href="/" aria-label="Mahadum360 home"><img src="/images/m360_logo_official.png" alt="Mahadum360"></a>
    <a href="/families">Families</a><a href="/schools">Schools</a><a href="/institutions">Institutions</a><a href="/languages">Languages</a><a href="/guides">Guides</a><a href="/pricing">Pricing</a><a href="/contact">Contact</a><a href="/login">Sign in</a><a href="/register" class="cta">Start free</a>
</nav></header>
<main id="main">@yield('content')</main>
<footer>
    <p>Learn the language. Live the culture. Connect the generations.</p>
    <nav aria-label="Languages"><a href="/learn-igbo">Igbo</a><a href="/learn-yoruba">Yorùbá</a><a href="/learn-hausa">Hausa</a><a href="/guides">Guides</a><a href="/vote">Vote for the next language</a></nav>
    <nav aria-label="Social and support">
      <a href="https://www.youtube.com/@mahadum360"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M22 7s-.3-2-2-2.3C17.5 4.4 12 4.4 12 4.4s-5.5 0-8 .3C2.3 5 2 7 2 7s-.4 2.3-.4 5 .4 5 .4 5 .3 2 2 2.3c2.5.3 8 .3 8 .3s5.5 0 8-.3c1.7-.3 2-2.3 2-2.3s.4-2.3.4-5-.4-5-.4-5ZM10 16V8l6 4Z"/></svg>YouTube</a>
      <a href="https://www.instagram.com/mahadum360/"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="18" cy="6" r="1"/></svg>Instagram</a>
      <a href="https://www.facebook.com/Mahadum360/"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M14 22v-9h3l.5-4H14V7c0-1 .3-2 2-2h2V1h-3c-3 0-5 2-5 5v3H7v4h3v9Z"/></svg>Facebook</a>
      <a href="https://wa.me/2349028111111?text=Hello%20Mahadum360%2C%20I%27d%20like%20to%20learn%20more.">Chat with us on WhatsApp</a>
    </nav>
    <nav aria-label="Legal"><a href="/child-safety">Child safety</a><a href="/accessibility">Accessibility</a><a href="/privacy">Privacy</a><a href="/terms">Terms</a></nav>
    <p>© Mahadum360, RC 9601595. {{ date('Y') }} · Lagos, Nigeria</p>
</footer>
</body></html>
