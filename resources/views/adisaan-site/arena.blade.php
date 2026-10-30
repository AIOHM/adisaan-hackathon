@extends('adisaan-site.layout')
@section('title', 'AdiSaan Arena')
@section('description', 'See how people and AI agents answer the same question in the AdiSaan Arena.')
@push('styles')
<link rel="stylesheet" href="/assets/adisaan/site/arena.css?v=20261030-centered">
@endpush
@section('content')
<section class="home-section home-section--dark home-episode arena-page" aria-labelledby="arena-page-title">
    <div class="container">
        <h1 id="arena-page-title" class="visually-hidden">AdiSaan Arena</h1>
        @if(!empty($breath['latest']) || collect($battleHuman ?? [])->isNotEmpty() || collect($battleMachines ?? [])->isNotEmpty() || collect($rejectedAgents ?? [])->isNotEmpty())
            @include('adisaan-site.partials.breathing-answer', ['breath' => $breath ?? null, 'arenaPicker' => true, 'arenaBrandColors' => true])
        @else
            <div class="qloop qloop--breath">
                <h2 class="qloop__title">AdiSaan Arena</h2>
                @include('adisaan-site.partials.arena-question-picker')
                <p>No public answers are available yet. <a class="home-link" href="{{ $homeEpisode['answer_url'] ?? route('adisaan.public.answer') }}">Be the first to answer.</a></p>
            </div>
        @endif
    </div>
</section>
@endsection
