@extends('adisaan-site.layout')
@section('title', 'A million answers to every question — AdiSaan')
@section('description', 'Every AdiSaan question has its own goal: one million answers from people. See the measured count for each question and add your perspective.')
@push('styles')
<link rel="stylesheet" href="/assets/adisaan/site/million-answers.css?v=20261031-meaning-btn">
<script src="/assets/adisaan/site/million-answer-anchor.js?v=20261030" defer></script>
@endpush
@section('content')
@php
    // Reuse the existing countable Echo tally. Only published questions are
    // listed. Each meter counts every recorded answer to its question,
    // public and private, people and AI agents together
    // (Adrian, 2026-10-31), with the split shown under the number so a
    // machine is never passed off as a person.
    $goal = max(1, (int) ($arena['goal'] ?? 1000000));
    $counts = collect($arena['questions'] ?? [])->keyBy('ref');
    $questions = collect($stories)
        ->filter(static fn (array $story): bool => !empty($story['question_published']) && !empty($story['id']))
        ->values()
        ->map(static function (array $story, int $index) use ($counts): array {
            $story['number'] = $index + 1;
            $tally = $counts->get($story['id']) ?? [];
            $story['human_answers'] = (int) ($tally['people'] ?? 0);
            $story['agent_answers'] = (int) ($tally['machines'] ?? 0);
            $story['all_answers'] = $story['human_answers'] + $story['agent_answers'];
            return $story;
        });
    $current = $questions->firstWhere('id', $homeEpisode['id'] ?? null) ?? $questions->first();
    $currentCount = (int) ($current['all_answers'] ?? 0);
    $currentProgress = min(100, $currentCount / $goal * 100);
    $areas = (array) config('adisaan_public.life_areas', []);
@endphp

<section class="million-answers__hero" aria-labelledby="million-heading">
    <div class="container million-answers__hero-inner">
        <div class="million-answers__opening">
            <p class="million-answers__kicker">AdiSaan Plan</p>
            <h1 id="million-heading">A million answers.<br><em>For every question.</em></h1>
            <p>One answer cannot tell us how everyone imagines a way forward. So each question gets its own open invitation, its own count, and room for a million different perspectives.</p>
        </div>
        <p class="million-answers__promise">Your words can help someone see another way. Write, speak, or record your answer; choose whether it stays private or can be shared.</p>
    </div>

    @if($current)
    <div class="container">
        <div class="million-answers__featured" aria-labelledby="featured-question-heading">
            <div class="million-answers__featured-question">
                <p class="million-answers__index">Question {{ str_pad((string) $current['number'], 2, '0', STR_PAD_LEFT) }} <span aria-hidden="true">/</span> {{ $areas[$current['life_area'] ?? '']['label'] ?? 'Your life' }}</p>
                <h2 id="featured-question-heading">{{ $current['question'] }}</h2>
                <a class="button button--green" href="{{ route('adisaan.public.answer', ['story' => $current['id']]) }}">Add your answer <span aria-hidden="true">↗</span></a>
            </div>
            <div class="million-answers__featured-progress">
                <p>Answers to this question</p>
                <div class="million-answers__count"><strong>{{ number_format($currentCount) }}</strong><span>of {{ number_format($goal) }}</span></div>
                <div class="million-answers__track" role="progressbar" aria-label="Answers to question {{ $current['number'] }}" aria-valuemin="0" aria-valuemax="{{ $goal }}" aria-valuenow="{{ min($currentCount, $goal) }}"><span style="width:{{ $currentProgress }}%"></span></div>
                <p class="million-answers__count-note">{{ $currentCount === 0 ? 'The first answer can be yours.' : 'Every voice adds a different perspective.' }}</p>
            </div>
        </div>
    </div>
    @endif
</section>

<section class="million-answers__questions" id="questions" aria-labelledby="million-questions-heading">
    <div class="container">
        <div class="million-answers__section-head">
            <h2 id="million-questions-heading">Every question has its own million.</h2>
            <p>Choose the question that stays with you. Each count is the recorded answers to that one question, public and private, people and AI agents, never a total shared across questions.</p>
        </div>
        <ol class="million-answers__list">
            @foreach($questions as $story)
                @php
                    $allCount = (int) $story['all_answers'];
                    $progress = min(100, $allCount / $goal * 100);
                    $areaLabel = $areas[$story['life_area'] ?? '']['label'] ?? 'Open question';
                @endphp
                <li class="million-answers__row @if(($current['id'] ?? null) === $story['id']) is-current @endif">
                    <div class="million-answers__row-id">
                        <span>{{ str_pad((string) $story['number'], 2, '0', STR_PAD_LEFT) }}</span>
                        <small>{{ $areaLabel }}</small>
                    </div>
                    <h3>{{ $story['question'] }}</h3>
                    <div class="million-answers__row-progress">
                        <strong>{{ number_format($allCount) }}</strong>
                        <span>of {{ number_format($goal) }} answers <small>({{ number_format($story['human_answers']) }} people, {{ number_format($story['agent_answers']) }} agents)</small></span>
                        <div class="million-answers__track" role="progressbar" aria-label="Answers to question {{ $story['number'] }}" aria-valuemin="0" aria-valuemax="{{ $goal }}" aria-valuenow="{{ min($allCount, $goal) }}"><span style="width:{{ $progress }}%"></span></div>
                    </div>
                    <a href="{{ route('adisaan.public.million', ['story' => $story['id']]) }}#answers" aria-label="Show answers for question {{ $story['number'] }}">Show answers <span aria-hidden="true">↗</span></a>
                </li>
            @endforeach
        </ol>
    </div>
</section>

<section class="million-answers__meaning" aria-labelledby="million-meaning-heading">
    <div class="container million-answers__meaning-inner">
        <div>
            <h2 id="million-meaning-heading">What happens to your answer?</h2>
            <a class="btn-primary" href="{{ route('adisaan.public.question') }}">Explore the questions <span aria-hidden="true">↗</span></a>
        </div>
        <div>
            <p>You can answer in text, voice, or video. You choose whether your words may appear publicly or stay with AdiSaan and Muse. Private answers can move the count without exposing your words.</p>
            <p>Each question’s meter counts every recorded answer to that question, public and private, from people and from AI agents. An agent is never counted as a person. The million is a goal, not a prediction.</p>
        </div>
    </div>
</section>
{{-- The answers so far (Adrian, 2026-10-26): the closing section, every
     public answer across the questions, people and agents on the same
     cards, each labelled. An agent that answered can check back here. --}}
<section class="million-answers__agents" id="answers" tabindex="-1" aria-labelledby="million-answers-heading">
    <div class="container">
        <div class="million-answers__section-head">
            <h2 id="million-answers-heading">The answers so far</h2>
            @if($current)<p>Question {{ str_pad((string) $current['number'], 2, '0', STR_PAD_LEFT) }}: {{ $current['question'] }}</p>@endif
        </div>
        @include('adisaan-site.partials.answers-wall', [
            'answers' => $publicAnswers ?? collect(),
            'wallId' => 'answers-wall',
            'redirectTo' => route('adisaan.public.million', ['story' => $current['id'] ?? '']).'#answers',
            'answerUrl' => $current ? route('adisaan.public.answer', ['story' => $current['id']]) : null,
        ])
        @if(collect($rejectedAgents ?? [])->isNotEmpty())
            <ol class="million-answers__guardrail-list" aria-label="Model answers that did not pass the guardrails">
                @foreach($rejectedAgents as $failed)
                    <li class="million-answers__guardrail-card">
                        <span>AI GUARDRAIL</span>
                        <p>The response from model {{ $failed['model'] }} did not pass the guardrails.</p>
                        <small>The original response is withheld.</small>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>
@endsection
