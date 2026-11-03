@extends('adisaan-site.layout')
@section('title', __('site.ui.arena_title'))
@section('description', __('site.ui.arena_description'))
@push('styles')
<link rel="stylesheet" href="/assets/adisaan/site/arena.css?v=20261031-wide">
@endpush
@section('content')
<section class="home-section home-section--dark home-episode arena-page" aria-labelledby="arena-page-title">
    <div class="container">
        <h1 id="arena-page-title" class="visually-hidden">{{ __('site.ui.arena_title') }}</h1>
        @if(!empty($breath['latest']) || collect($battleHuman ?? [])->isNotEmpty() || collect($battleMachines ?? [])->isNotEmpty() || collect($rejectedAgents ?? [])->isNotEmpty())
            @include('adisaan-site.partials.breathing-answer', ['breath' => $breath ?? null, 'arenaPicker' => true, 'arenaBrandColors' => true])
        @else
            <div class="qloop qloop--breath">
                <h2 class="qloop__title">{{ __('site.ui.arena_title') }}</h2>
                @include('adisaan-site.partials.arena-question-picker')
                <p>{{ __('site.ui.arena_none') }} <a class="home-link" href="{{ $homeEpisode['answer_url'] ?? route('adisaan.public.answer') }}">{{ __('site.ui.arena_first') }}</a></p>
            </div>
        @endif
    </div>
</section>
@endsection
