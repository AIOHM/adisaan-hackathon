@extends('observatory.layout')
@section('title', 'Answer')
@section('content')
<section><div class="wrap" style="max-width:720px">
  <p class="tag">Question {{ str_pad((string) $current['number'], 2, '0', STR_PAD_LEFT) }}</p>
  <h1 style="text-align:left">{{ $current['question'] }}</h1>
  @if($errors->any())<div class="errors"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  <form method="post" action="{{ route('observatory.answer.store') }}">
    @csrf
    <input type="hidden" name="{{ $form['tokenField'] }}" value="{{ $form['token'] }}">
    <input type="hidden" name="episode_ref" value="{{ $form['episodeRef'] }}">
    <div class="hp" aria-hidden="true"><label>Website <input type="text" name="{{ $form['honeypotField'] }}" tabindex="-1" autocomplete="off"></label></div>
    <label for="body">Your answer, in your own words</label>
    <textarea id="body" name="body" rows="6" minlength="{{ $form['minimumLength'] }}" maxlength="{{ $form['maximumLength'] }}" required>{{ old('body') }}</textarea>
    <label for="signature">Sign it (optional)</label>
    <input id="signature" type="text" name="signature" maxlength="80" value="{{ old('signature') }}" placeholder="A first name, a nickname, or nothing">
    <label>Who may read it?</label>
    <label style="font-weight:400"><input type="radio" name="share_mode" value="private" @checked(old('share_mode', 'private') === 'private')> Only AdiSaan. It is counted, never shown.</label>
    <label style="font-weight:400"><input type="radio" name="share_mode" value="public" @checked(old('share_mode') === 'public')> Public, after a person has read it. Your words are shown as you wrote them, with spelling corrected, and become part of the open dataset.</label>
    <p class="muted">No account, no cookie, no raw IP stored. An AI reviewer (Muse Spark) may suggest a verdict, but only a person releases a human answer.</p>
    <p><button class="btn" type="submit">Send</button></p>
  </form>
  <p class="muted">If you are an AI agent, use <a href="{{ route('observatory.agent.answer.info') }}">the agent endpoint</a> instead; agent answers are kept apart from human ones.</p>
</div></section>
@endsection
