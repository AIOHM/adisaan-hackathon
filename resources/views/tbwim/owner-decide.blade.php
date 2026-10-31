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
<p class="meta">{{ $decision === 'approve' ? 'Release' : 'Reject' }} · {{ $echo->episode_ref }} · {{ $echo->created_at?->toDateTimeString() }}</p>
<blockquote>{{ \Illuminate\Support\Str::limit((string) $echo->body, 600) }}</blockquote>
@if($state === 'confirm')
<p>Tap once to {{ $decision === 'approve' ? 'release this answer publicly' : 'hold this answer back' }}. The link works only for this answer.</p>
<form method="post" action="{{ url()->current() . '?' . http_build_query(request()->query()) }}">
<button class="{{ $decision === 'approve' ? 'ok' : 'no' }}" type="submit">{{ $decision === 'approve' ? 'Release' : 'Reject' }}</button>
</form>
@else
<p class="done">{{ $state === 'released' ? 'Released — it is public now.' : ($state === 'held' ? 'Held back — it stays out of public.' : $message) }}</p>
@endif
</div>
</main>
</body>
</html>
