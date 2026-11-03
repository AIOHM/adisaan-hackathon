<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Review answer — TBWIM</title>
<style>
body{font-family:system-ui,sans-serif;background:#0d1512;color:#f4f0e8;margin:0;padding:2rem 1rem}
main{max-width:36rem;margin:0 auto}
.card{background:#14201b;border:1px solid #2a3b32;border-radius:.75rem;padding:1.5rem}
.meta{color:#8ba383;font-size:.85rem;margin:0 0 .5rem}
blockquote{border-left:3px solid #9adc9f;margin:1rem 0;padding:.25rem 0 .25rem 1rem}
.row{display:flex;gap:.75rem;margin-top:1.25rem}
button{flex:1;font-size:1rem;padding:.8rem;border:none;border-radius:.5rem;cursor:pointer}
.ok{background:#9adc9f;color:#0d1512}
.no{background:transparent;color:#e88;border:1px solid #e88}
.done{font-size:1.1rem}
</style>
</head>
<body>
<main>
<div class="card">
<h1>Pending decisions</h1>
<p>This private link expires after seven days. Open a newer Telegram alert for a fresh link.</p>
@foreach(['thought' => $thoughts, 'signal' => $signals] as $kind => $rows)
<h2>{{ $kind === 'thought' ? 'Thoughts' : 'Future questions' }}</h2>
@forelse($rows as $row)<p>{{ $row->question }}</p><p><a href="{{ $alerts->draftUrl($kind, $row->id, 'approve') }}">Review approval</a> · <a href="{{ $alerts->draftUrl($kind, $row->id, 'reject') }}">Review rejection</a></p>@empty<p>None waiting.</p>@endforelse
@endforeach
<h2>Human answers</h2>
@forelse($answers as $answer)<p>{{ $answer->body }}</p><p><a href="{{ $alerts->decideUrl($answer, 'approve') }}">Review release</a> · <a href="{{ $alerts->decideUrl($answer, 'reject') }}">Review rejection</a></p>@empty<p>None waiting.</p>@endforelse
</div></main></body></html>
