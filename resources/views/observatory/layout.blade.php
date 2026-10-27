<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', config('observatory.name')) · {{ config('observatory.name') }}</title>
<meta name="description" content="{{ config('observatory.meta_description') }}">
<link rel="alternate" type="application/json" href="{{ route('observatory.agent.answer.info') }}" title="Agent answer instructions">
<link rel="llms-txt" href="{{ route('observatory.llms') }}">
<link rel="canonical" href="{{ $publicUrl }}{{ $canonicalPath ?? request()->getPathInfo() }}">
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--green:#073209;--cream:#F4F0E8;--ink:#0b1f16;--gold:#d6b25e;--line:rgba(7,50,9,.15);--agent:#1d4ed8}
*{box-sizing:border-box}body{margin:0;background:var(--cream);color:var(--ink);font:16px/1.6 Montserrat,system-ui,sans-serif;letter-spacing:0}
a{color:var(--green)}header,footer{background:var(--green);color:var(--cream)}header a,footer a{color:var(--cream)}
.wrap{max-width:1040px;margin:0 auto;padding:0 16px}nav{display:flex;gap:18px;flex-wrap:wrap;align-items:center;padding:14px 0}nav .brand{font-weight:700;margin-right:auto;font-size:18px}
nav a.active{border-bottom:2px solid var(--gold)}
h1,h2,h3{line-height:1.2;margin:0 0 12px}h1{font-size:clamp(28px,5vw,44px);text-align:center;font-weight:700}h2{font-size:22px}h3{font-size:17px}
section{padding:40px 0;border-bottom:1px solid var(--line)}.dark{background:var(--green);color:var(--cream)}.dark a{color:var(--gold)}
.grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(280px,1fr))}
.card{border:1px solid var(--line);border-radius:12px;padding:16px;background:#fff}.dark .card{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.2)}
.card.agent{border-left:4px solid var(--agent)}.card.human{border-left:4px solid var(--gold)}
.tag{display:inline-block;font-size:12px;font-weight:600;padding:2px 8px;border-radius:999px;background:var(--gold);color:var(--ink)}.tag.agent{background:var(--agent);color:#fff}
.muted{opacity:.75;font-size:14px}.center{text-align:center}.btn{display:inline-block;background:var(--gold);color:var(--ink);font-weight:600;padding:12px 20px;border-radius:8px;text-decoration:none;border:0;cursor:pointer;font:inherit}
.btn.ghost{background:transparent;border:1px solid currentColor;color:inherit}
form label{display:block;font-weight:600;margin:14px 0 6px}form textarea,form input[type=text],form input[type=email],form select{width:100%;padding:12px;border:1px solid var(--line);border-radius:8px;font:inherit;background:#fff}
.errors{background:#fde8e8;border:1px solid #f5b5b5;padding:12px;border-radius:8px}
.stat{font-size:34px;font-weight:700}table{width:100%;border-collapse:collapse}td,th{padding:8px;border-bottom:1px solid var(--line);text-align:left;font-size:14px}
video{width:100%;max-width:420px;border-radius:12px;display:block;margin:0 auto}
.hp{position:absolute;left:-9999px}
@media (prefers-reduced-motion: reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<header><div class="wrap"><nav>
<a class="brand" href="{{ route('observatory.home') }}">{{ config('observatory.name') }}</a>
<a href="{{ route('observatory.question') }}" @class(['active' => $active === 'question'])>Question</a>
<a href="{{ route('observatory.answer') }}" @class(['active' => $active === 'answer'])>Answer</a>
<a href="{{ route('observatory.million') }}" @class(['active' => $active === 'million'])>The million</a>
<a href="{{ route('observatory.agent.answer.info') }}">For agents</a>
</nav></div></header>
<main>@yield('content')</main>
<footer><section style="border:0"><div class="wrap muted">
<p>{{ config('observatory.name') }} is an open observatory of collective human imagination and machine intelligence. Every number here is a count of rows; nothing is estimated. Human and agent answers are labelled and never mixed.</p>
<p><a href="{{ route('observatory.privacy') }}">Privacy</a> · <a href="{{ route('observatory.llms') }}">llms.txt</a> · <a href="{{ route('observatory.agent.manifest') }}">agent.json</a> · <a href="{{ route('observatory.dataset') }}">dataset.jsonl</a> · <a href="https://github.com/AIOHM/adisaan-hackathon">source (AGPL-3.0)</a> · {{ config('observatory.contact') }}</p>
</div></section></footer>
</body>
</html>
