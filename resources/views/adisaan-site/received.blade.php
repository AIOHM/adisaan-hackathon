@extends('adisaan-site.layout')
@section('title', 'Thank you — AdiSaan')
@push('styles')<link rel="stylesheet" href="/assets/adisaan/site/received-journey.css?v=20261025-3">@endpush
@section('content')
@php
    $mode = session('shared_mode', 'public');
    $ap = config('adisaan_public.home.answer_page', []);
    $people = (int) ($answeredSplit['people'] ?? 0);
    $machines = (int) ($answeredSplit['machines'] ?? 0);
    $qLead = $answeredStory['lead'] ?? $answeredStory['question'] ?? null;
    $shareUrl = rtrim((string) config('adisaan_public.url'), '/')
        . ($answeredStory ? '/answer?story='.$answeredStory['id'] : '/million');
    $shareText = $qLead
        ? 'I just answered: "'.$qLead.'" on AdiSaan, where people answer the same question as the machines. '
          .number_format($people).' '.($people === 1 ? 'person' : 'people').' so far. What is your way?'
        : 'I just answered on AdiSaan, where people answer the same question as the machines. What is your way?';
    $tokenTotal = max(0, (int) ($gratitude['spendable'] ?? 0) + (int) ($gratitude['pending'] ?? 0));
    $reasonLabels = [
        'answer' => 'Your answer',
        'why' => 'Your reason',
        'first_answer' => 'First answer to a question',
        'media_bonus' => 'Voice or video answer',
        'claim' => 'Claiming your tokens',
        'spend' => 'Your question asked',
    ];
@endphp
<section class="home-section home-section--dark received-page" aria-labelledby="received-heading">
    @if(session('just_answered'))
        <div class="received-celebration" aria-hidden="true">
            @for($i = 0; $i < 20; $i++)
                <span style="--piece:{{ $i }}"></span>
            @endfor
        </div>
    @endif
    <div class="container received-layout">
        <div class="received-story">
            <p class="received-story__eyebrow">{{ ($hasSubmission ?? false) ? 'Your voice joined the conversation' : 'The conversation is open' }}</p>
            <h1 id="received-heading" @if(session('just_answered')) data-analytics-event="answer_submitted" data-analytics-mode="{{ $mode }}" data-analytics-episode="{{ session('shared_question', '') }}" @endif>{{ ($hasSubmission ?? false) ? ($ap['received_title'] ?? 'Thank you.') : 'Your voice belongs here.' }}</h1>
            @unless($hasSubmission ?? false)
                <p class="home-lead">Answer a question to join the conversation. Your receipt and tokens will appear here.</p>
            @endunless

            @if($canAskWhy ?? false)
                <form id="why" class="received-why" method="post" action="{{ route('adisaan.public.received') }}">
                    @csrf
                    <label class="received-why__label" for="why-field">And why that way?</label>
                    <p class="received-why__hint">One sentence is enough. Your reason earns {{ $gratitude['why_reward'] }} more tokens.</p>
                    <textarea id="why-field" name="why" rows="3" minlength="10" maxlength="2000" required placeholder="Because…">{{ old('why') }}</textarea>
                    @if(session('why_error'))<p class="received-why__error" role="alert">{{ session('why_error') }}</p>@endif
                    <button type="submit" class="btn-primary">Add your reason <span aria-hidden="true">↗</span></button>
                </form>
            @elseif(!empty($existingWhy))
                <div id="why" class="received-why received-why--done">
                    <p class="received-why__label">Your reason</p>
                    <blockquote>{{ $existingWhy }}</blockquote>
                    @if(session()->has('why_saved'))<p class="received-why__ok" role="status">Saved.@if((int) session('why_saved') > 0) {{ (int) session('why_saved') }} tokens added.@endif</p>@endif
                </div>
            @endif

            @if($qLead)
                <div class="received-context">
                    <p class="received-context__label">The question you answered</p>
                    <p class="received-q">{{ $qLead }}</p>
                    <div class="received-score" role="group" aria-label="{{ number_format($people) }} from people, {{ number_format($machines) }} from machines, on this question">
                        <div class="received-score__side"><strong>{{ number_format($people) }}</strong><span>{{ $people === 1 ? 'person' : 'people' }}</span></div>
                        <span class="received-score__vs" aria-hidden="true">and</span>
                        <div class="received-score__side received-score__side--machines"><strong>{{ number_format($machines) }}</strong><span>{{ $machines === 1 ? 'machine' : 'machines' }}</span></div>
                    </div>
                </div>
            @endif

            @if($hasSubmission ?? false)
            <div class="received-actions">
                <button class="btn-primary" type="button" data-share
                        data-share-url="{{ $shareUrl }}"
                        data-share-title="AdiSaan — people and machines"
                        data-share-text="{{ $shareText }}">Share the question <span aria-hidden="true">↗</span></button>
                @if($nextStory)
                    <a class="home-link" href="{{ route('adisaan.public.answer', ['story' => $nextStory['id']]) }}">Answer the next question <span aria-hidden="true">&nearr;</span></a>
                @endif
            </div>
            @else
                <div class="received-actions"><a class="btn-primary" href="{{ route('adisaan.public.answer') }}">Answer a question <span aria-hidden="true">↗</span></a></div>
            @endif
            <p class="received-page__links"><a class="home-link" href="{{ route('adisaan.public.million') }}">{{ $ap['see_answers'] ?? 'See the answers' }}</a> · <a class="home-link" href="{{ route('adisaan.public.question') }}">{{ $ap['see_episodes'] ?? 'Explore the questions' }}</a></p>
        </div>

        @if($gratitude['enabled'] ?? false)
            <aside class="received-side" aria-label="Your Gratitude Tokens and next step">
                <div id="claim" class="received-wallet">
                    <h2>Gratitude Tokens</h2>
                    <div class="received-wallet__total" aria-live="polite"><strong>{{ number_format($tokenTotal) }}</strong><span>in your wallet</span></div>
                    <div class="received-wallet__split" aria-label="Token status">
                        <p><strong>{{ number_format((int) $gratitude['pending']) }}</strong><span>waiting to be claimed or reviewed</span></p>
                        <p><strong>{{ number_format((int) $gratitude['spendable']) }}</strong><span>ready to use</span></p>
                    </div>
                    @if($answerWasShort && $tokenTotal === 0)
                        <p class="received-wallet__notice">Your answer was saved. Text answers earn tokens from {{ $gratitude['minimum_length'] }} characters; your reason can still earn tokens.</p>
                    @elseif($answerAwaitingReview && $gratitude['pending'] > 0)
                        <p class="received-wallet__notice">Your recording is saved. Its tokens will be ready after review and claim.</p>
                    @endif

                    @if(!empty($gratitude['history']))
                        <h3 class="received-wallet__subhead">How this total was made</h3>
                        <ul class="received-ledger">
                            @foreach($gratitude['history'] as $entry)
                                <li><span>{{ $reasonLabels[$entry['reason']] ?? ucfirst(str_replace('_', ' ', $entry['reason'])) }}</span><strong>{{ $entry['amount'] > 0 ? '+' : '' }}{{ $entry['amount'] }}</strong></li>
                            @endforeach
                        </ul>
                    @endif

                    @if($gratitude['claimed'])
                        @if(session()->has('claim_saved'))
                            <p class="received-wallet__ok" role="status">Claimed. {{ (int) session('claim_saved') > 0 ? '+'.(int) session('claim_saved').' tokens, and ' : '' }}your answers are yours now.</p>
                        @endif
                        <a class="home-link" href="{{ route('adisaan.public.members') }}">Open your members area <span aria-hidden="true">&nearr;</span></a>
                    @elseif($hasSubmission ?? false)
                        <p class="received-wallet__line">Claim this anonymous answer with your first name and email. You receive {{ $gratitude['claim_reward'] }} more tokens; confirmed tokens become usable.</p>
                        <form class="received-claim" method="post" action="{{ route('adisaan.public.received') }}">
                            @csrf
                            <input type="hidden" name="form" value="claim">
                            <div class="received-claim__row">
                                <label class="visually-hidden" for="claim-name">Your first name</label>
                                <input id="claim-name" type="text" name="name" maxlength="60" required autocomplete="given-name" placeholder="Your first name">
                                <label class="visually-hidden" for="claim-email">Your email</label>
                                <input id="claim-email" type="email" name="email" maxlength="255" required autocomplete="email" inputmode="email" placeholder="you@example.com">
                                <button type="submit" class="btn-primary">Claim tokens</button>
                            </div>
                            @if(session('claim_error'))<p class="received-wallet__error" role="alert">{{ session('claim_error') }}</p>@endif
                            <p class="received-claim__fine">Your answer stays anonymous on the site. Claiming tokens does not subscribe you to a newsletter. <a href="{{ route('adisaan.public.privacy') }}">Privacy</a></p>
                        </form>
                    @else
                        <p class="received-wallet__line">Your answers will collect here. <a class="home-link" href="{{ route('adisaan.public.answer') }}">Answer a question <span aria-hidden="true">&nearr;</span></a></p>
                    @endif
                </div>

                <div class="received-token-key" aria-labelledby="received-token-key-heading">
                    <h2 id="received-token-key-heading">How tokens work</h2>
                    <dl>
                        <div><dt>Thoughtful answer</dt><dd>+{{ $gratitude['answer_reward'] }}</dd></div>
                        <div><dt>First answer to a question</dt><dd>+{{ $gratitude['first_reward'] }}</dd></div>
                        <div><dt>Tell us why</dt><dd>+{{ $gratitude['why_reward'] }}</dd></div>
                        <div><dt>Voice or video after review</dt><dd>+{{ $gratitude['media_reward'] }}</dd></div>
                    </dl>
                    <p>Text answers earn tokens from {{ $gratitude['minimum_length'] }} characters. Claiming adds {{ $gratitude['claim_reward'] }}. At {{ number_format($gratitude['ask_cost']) }} usable tokens, AdiSaan asks your question of people and machines. These are participation credits, not money.</p>
                </div>

            </aside>
        @endif
    </div>

    @if($machineAnswers->isNotEmpty() || $qLead)
        <div class="container received-after">
            @if($machineAnswers->isNotEmpty())
                <h2 class="received-machines__title">What the machines said to the same question</h2>
                <ul class="received-machines">
                    @foreach($machineAnswers as $m)
                        <li>
                            <p class="received-machines__who">{{ $m['agent_model'] ?? 'AI model' }}@if(!empty($m['agent_developer'])) <span>· {{ $m['agent_developer'] }}</span>@endif</p>
                            <p class="received-machines__body">{{ $m['body'] }}</p>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="received-pending">No machine has answered this one yet. When they do, yours sits beside theirs.</p>
            @endif
        </div>
    @endif
</section>
@endsection
