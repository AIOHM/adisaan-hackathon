@extends('adisaan-site.layout')
@section('title', 'AdiSaan Arena')
@section('description', 'See how people and AI agents answer the same question in the AdiSaan Arena.')
@section('content')
<section class="home-section home-episode" aria-labelledby="arena-page-title">
    <div class="container">
        <h1 id="arena-page-title" class="visually-hidden">AdiSaan Arena</h1>
        @if(count($stories ?? []) > 1)
            <form class="answer-question-picker" action="{{ route('adisaan.public.arena') }}" method="get">
                <label for="arena-question">Choose a question</label>
                <div><select id="arena-question" name="story">
                    @foreach($stories as $story)
                        <option value="{{ $story['id'] }}" @selected(($homeEpisode['id'] ?? null) === $story['id'])>{{ $story['question'] }}</option>
                    @endforeach
                </select><button type="submit">Choose</button></div>
            </form>
        @endif
        @if(!empty($breath['latest']) || collect($battleHuman ?? [])->isNotEmpty() || collect($battleMachines ?? [])->isNotEmpty() || collect($rejectedAgents ?? [])->isNotEmpty())
            @include('adisaan-site.partials.breathing-answer', ['breath' => $breath ?? null])
        @else
            <div class="qloop qloop--breath">
                <h2 class="qloop__title">AdiSaan Arena</h2>
                <p>No public answers are available yet. <a class="home-link" href="{{ $homeEpisode['answer_url'] ?? route('adisaan.public.answer') }}">Be the first to answer.</a></p>
            </div>
        @endif
    </div>
</section>
@endsection
