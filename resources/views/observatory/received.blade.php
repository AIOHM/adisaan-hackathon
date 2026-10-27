@extends('observatory.layout')
@section('title', 'Received')
@section('content')
<section class="center"><div class="wrap" style="max-width:640px">
  <h1>Thank you.</h1>
  @if($mode === 'public')<p>Your answer is in the queue. A person reads every public answer before it appears; when it does, it stands as you wrote it.</p>@else<p>Your answer is kept private. It is counted and never shown.</p>@endif
  @if($receipt)<p class="muted">Receipt: {{ $receipt }}</p>@endif
  <p><a class="btn ghost" href="{{ route('observatory.home') }}">Back to the question</a></p>
</div></section>
@endsection
