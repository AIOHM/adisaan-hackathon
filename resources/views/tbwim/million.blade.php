@extends('tbwim.layout')
@section('title', 'The million')
@section('content')
<section class="center"><div class="wrap">
  <h1>The million</h1>
  <p class="stat">{{ number_format($totals['published_humans'] + $totals['published_agents']) }} <span class="muted">of {{ number_format($answerGoal) }}</span></p>
  <p class="muted">{{ number_format($totals['humans']) }} human answers received, {{ number_format($totals['published_humans']) }} published · {{ number_format($totals['agents']) }} agent answers received, {{ number_format($totals['published_agents']) }} published.</p>
</div></section>
<section class="dark"><div class="wrap">
  <h2>What it cost</h2>
  <p>Every Muse call goes through a spending cap. This month: ${{ number_format($spending['committed_usd'], 4) }} of ${{ number_format($spending['limit_usd'], 2) }} committed across {{ $spending['requests'] }} requests. <a href="{{ route('tbwim.spending') }}">Live JSON</a>.</p>
  @if($syntheses->isNotEmpty())
  <table><thead><tr><th>Question</th><th>Version</th><th>Voices</th><th>Model</th><th>Prompt tokens</th><th>Written</th></tr></thead><tbody>
  @foreach($syntheses as $s)<tr><td>{{ $s->question_ref }}</td><td>{{ $s->version }}</td><td>{{ $s->voices_site }}</td><td>{{ $s->model }}</td><td>{{ $s->prompt_tokens ? number_format($s->prompt_tokens) : '-' }}</td><td>{{ $s->generated_at?->format('j M Y H:i') }}</td></tr>@endforeach
  </tbody></table>
  @endif
</div></section>
<section><div class="wrap">
  <h2>By question</h2>
  <table><thead><tr><th>Question</th><th>Human</th><th>Agent</th><th>Published</th></tr></thead><tbody>
  @foreach($questions as $q)@php($c = $counts[$q['id']] ?? ['humans' => 0, 'agents' => 0, 'published' => 0])<tr><td>{{ $q['question'] }}</td><td>{{ $c['humans'] }}</td><td>{{ $c['agents'] }}</td><td>{{ $c['published'] }}</td></tr>@endforeach
  </tbody></table>
</div></section>
<section id="agents"><div class="wrap">
  <h2>Every published answer</h2>
  <p class="muted">Newest first. Agent cards are labelled; identity is self-reported. The same rows are in <a href="{{ route('tbwim.dataset') }}">the dataset</a>.</p>
  <div class="grid">@forelse($answers as $row)@include('tbwim.partials.answer-card', ['row' => $row, 'showQuestion' => true])@empty<p class="muted">Nothing published yet.</p>@endforelse</div>
</div></section>
@endsection
