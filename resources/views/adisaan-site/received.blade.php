@extends('adisaan-site.layout')
@section('title', __('site.ui.received_title'))
@push('styles')<link rel="stylesheet" href="/assets/adisaan/site/received-journey.css?v=20261025-3">@endpush
@section('content')
@php
    $mode = session('shared_mode', 'public');
    $ap = $home['answer_page'] ?? config('adisaan_public.home.answer_page', []);
    $people = (int) ($answeredSplit['people'] ?? 0);
    $machines = (int) ($answeredSplit['machines'] ?? 0);
    $qLead = $answeredStory['lead'] ?? $answeredStory['question'] ?? null;
    $shareUrl = rtrim((string) config('adisaan_public.url'), '/')
        . ($answeredStory ? '/answer?story='.$answeredStory['id'] : '/million');
    $shareText = $qLead
        ? __('site.ui.share_text', ['lead' => $qLead, 'count' => number_format($people).' '.($people === 1 ? __('site.ui.person') : __('site.ui.people'))])
        : __('site.ui.share_text_plain');
    $tokenTotal = max(0, (int) ($gratitude['spendable'] ?? 0) + (int) ($gratitude['pending'] ?? 0));
    $reasonLabels = [
        'answer' => __('site.ui.reason_answer'),
        'why' => __('site.ui.reason_why'),
        'first_answer' => __('site.ui.reason_first'),
        'media_bonus' => __('site.ui.reason_media'),
        'claim' => __('site.ui.reason_claim'),
        'spend' => __('site.ui.reason_spend'),
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
            <p class="received-story__eyebrow">{{ ($hasSubmission ?? false) ? __('site.ui.joined') : __('site.ui.open_conversation') }}</p>
            <h1 id="received-heading" @if(session('just_answered')) data-analytics-event="answer_submitted" data-analytics-mode="{{ $mode }}" data-analytics-episode="{{ session('shared_question', '') }}" @endif>{{ ($hasSubmission ?? false) ? ($ap['received_title'] ?? __('site.ui.received_title')) : __('site.ui.voice_belongs') }}</h1>
            @if(session('just_unlocked'))
                <p class="received-unlock" role="status">{!! __('site.ui.unlock_now') !!} <a href="{{ route('adisaan.public.million') }}#answers">{{ __('site.ui.read_them') }} <span aria-hidden="true">↗</span></a></p>
            @endif
            @unless($hasSubmission ?? false)
                <p class="home-lead">{{ __('site.ui.answer_join') }}</p>
            @endunless

            @if($canAskWhy ?? false)
                <form id="why" class="received-why" method="post" action="{{ route('adisaan.public.received') }}">
                    @csrf
                    <label class="received-why__label" for="why-field">{{ __('site.ui.why_label') }}</label>
                    <p class="received-why__hint">{{ __('site.ui.why_hint', ['n' => $gratitude['why_reward']]) }}</p>
                    <textarea id="why-field" name="why" rows="3" minlength="10" maxlength="2000" required placeholder="{{ __('site.ui.why_placeholder') }}">{{ old('why') }}</textarea>
                    @if(session('why_error'))<p class="received-why__error" role="alert">{{ session('why_error') }}</p>@endif
                    <button type="submit" class="btn-primary">{{ __('site.ui.add_reason') }} <span aria-hidden="true">↗</span></button>
                </form>
            @elseif(!empty($existingWhy))
                <div id="why" class="received-why received-why--done">
                    <p class="received-why__label">{{ __('site.ui.your_reason') }}</p>
                    <blockquote>{{ $existingWhy }}</blockquote>
                    @if(session()->has('why_saved'))<p class="received-why__ok" role="status">{{ __('site.ui.saved') }}@if((int) session('why_saved') > 0) {{ __('site.ui.tokens_added', ['n' => (int) session('why_saved')]) }}@endif</p>@endif
                </div>
            @endif

            @if($qLead)
                <div class="received-context">
                    <p class="received-context__label">{{ __('site.ui.question_answered') }}</p>
                    <p class="received-q">{{ $qLead }}</p>
                    <div class="received-score" role="group" aria-label="{{ __('site.ui.score_aria', ['people' => number_format($people), 'machines' => number_format($machines)]) }}">
                        <div class="received-score__side"><strong>{{ number_format($people) }}</strong><span>{{ $people === 1 ? __('site.ui.person') : __('site.ui.people') }}</span></div>
                        <span class="received-score__vs" aria-hidden="true">{{ __('site.ui.and') }}</span>
                        <div class="received-score__side received-score__side--machines"><strong>{{ number_format($machines) }}</strong><span>{{ $machines === 1 ? __('site.ui.machine') : __('site.ui.machines') }}</span></div>
                    </div>
                </div>
            @endif

            @if($hasSubmission ?? false)
            <div class="received-actions">
                <button class="btn-primary" type="button" data-share
                        data-share-url="{{ $shareUrl }}"
                        data-share-title="{{ __('site.ui.share_title') }}"
                        data-share-text="{{ $shareText }}">{{ __('site.ui.share_question') }} <span aria-hidden="true">↗</span></button>
                @if($nextStory)
                    <a class="home-link" href="{{ route('adisaan.public.answer', ['story' => $nextStory['id']]) }}">{{ __('site.ui.answer_next') }} <span aria-hidden="true">&nearr;</span></a>
                @endif
            </div>
            @else
                <div class="received-actions"><a class="btn-primary" href="{{ route('adisaan.public.answer') }}">{{ __('site.ui.answer_question') }} <span aria-hidden="true">↗</span></a></div>
            @endif
            <p class="received-page__links"><a class="home-link" href="{{ route('adisaan.public.million') }}">{{ $ap['see_answers'] ?? __('site.ui.million_so_far') }}</a> · <a class="home-link" href="{{ route('adisaan.public.question') }}">{{ $ap['see_episodes'] ?? __('site.ui.explore_questions') }}</a></p>
        </div>

        @if($gratitude['enabled'] ?? false)
            <aside class="received-side" aria-label="{{ __('site.ui.wallet_aria') }}">
                <div id="claim" class="received-wallet">
                    <h2>{{ __('site.ui.gratitude_tokens') }}</h2>
                    <div class="received-wallet__total" aria-live="polite"><strong>{{ number_format($tokenTotal) }}</strong><span>{{ __('site.ui.in_wallet') }}</span></div>
                    <div class="received-wallet__split" aria-label="{{ __('site.ui.token_status') }}">
                        <p><strong>{{ number_format((int) $gratitude['pending']) }}</strong><span>{{ __('site.ui.waiting_tokens') }}</span></p>
                        <p><strong>{{ number_format((int) $gratitude['spendable']) }}</strong><span>{{ __('site.ui.ready_tokens') }}</span></p>
                    </div>
                    @if($answerWasShort && $tokenTotal === 0)
                        <p class="received-wallet__notice">{{ __('site.ui.short_answer_note', ['n' => $gratitude['minimum_length']]) }}</p>
                    @elseif($answerAwaitingReview && $gratitude['pending'] > 0)
                        <p class="received-wallet__notice">{{ __('site.ui.recording_saved_note') }}</p>
                    @endif

                    @if(!empty($gratitude['history']))
                        <h3 class="received-wallet__subhead">{{ __('site.ui.how_made') }}</h3>
                        <ul class="received-ledger">
                            @foreach($gratitude['history'] as $entry)
                                <li><span>{{ $reasonLabels[$entry['reason']] ?? ucfirst(str_replace('_', ' ', $entry['reason'])) }}</span><strong>{{ $entry['amount'] > 0 ? '+' : '' }}{{ $entry['amount'] }}</strong></li>
                            @endforeach
                        </ul>
                    @endif

                    @if($gratitude['claimed'])
                        @if(session()->has('claim_saved'))
                            <p class="received-wallet__ok" role="status">{{ __('site.ui.claimed') }} {{ (int) session('claim_saved') > 0 ? '+'.(int) session('claim_saved').' tokens, ' : '' }}{{ __('site.ui.claimed_rest') }}</p>
                        @endif
                        <a class="home-link" href="{{ route('adisaan.public.million') }}#answers">{{ __('site.ui.members_answers') }} <span aria-hidden="true">&nearr;</span></a>
                    @elseif($hasSubmission ?? false)
                        <p class="received-wallet__line">{{ __('site.ui.claim_line', ['n' => $gratitude['claim_reward']]) }}</p>
                        <form class="received-claim" method="post" action="{{ route('adisaan.public.received') }}">
                            @csrf
                            <input type="hidden" name="form" value="claim">
                            <div class="received-claim__row">
                                <label class="visually-hidden" for="claim-name">{{ __('site.ui.first_name') }}</label>
                                <input id="claim-name" type="text" name="name" maxlength="60" required autocomplete="given-name" placeholder="{{ __('site.ui.first_name') }}">
                                <label class="visually-hidden" for="claim-email">{{ __('site.ui.your_email') }}</label>
                                <input id="claim-email" type="email" name="email" maxlength="255" required autocomplete="email" inputmode="email" placeholder="you@example.com">
                                <button type="submit" class="btn-primary">{{ __('site.ui.claim_tokens') }}</button>
                            </div>
                            @if(session('claim_error'))<p class="received-wallet__error" role="alert">{{ session('claim_error') }}</p>@endif
                            <p class="received-claim__fine">{{ __('site.ui.claim_fine') }} <a href="{{ route('adisaan.public.privacy') }}">{{ __('site.ui.privacy') }}</a></p>
                        </form>
                    @else
                        <p class="received-wallet__line">{{ __('site.ui.answers_collect') }} <a class="home-link" href="{{ route('adisaan.public.answer') }}">{{ __('site.ui.answer_question') }} <span aria-hidden="true">&nearr;</span></a></p>
                    @endif
                </div>

                <div class="received-token-key" aria-labelledby="received-token-key-heading">
                    <h2 id="received-token-key-heading">{{ __('site.ui.how_tokens') }}</h2>
                    <dl>
                        <div><dt>{{ __('site.ui.thoughtful') }}</dt><dd>+{{ $gratitude['answer_reward'] }}</dd></div>
                        <div><dt>{{ __('site.ui.reason_first') }}</dt><dd>+{{ $gratitude['first_reward'] }}</dd></div>
                        <div><dt>{{ __('site.ui.tell_why') }}</dt><dd>+{{ $gratitude['why_reward'] }}</dd></div>
                        <div><dt>{{ __('site.ui.voice_video_review') }}</dt><dd>+{{ $gratitude['media_reward'] }}</dd></div>
                    </dl>
                    <p>{{ __('site.ui.tokens_rule', ['min' => $gratitude['minimum_length'], 'claim' => $gratitude['claim_reward'], 'cost' => number_format($gratitude['ask_cost'])]) }}</p>
                </div>

            </aside>
        @endif
    </div>

    @if($machineAnswers->isNotEmpty() || $qLead)
        <div class="container received-after">
            @if($machineAnswers->isNotEmpty())
                <h2 class="received-machines__title">{{ __('site.ui.machines_said') }}</h2>
                <ul class="received-machines">
                    @foreach($machineAnswers as $m)
                        <li>
                            <p class="received-machines__who">{{ $m['agent_model'] ?? __('site.ui.ai_model') }}@if(!empty($m['agent_developer'])) <span>· {{ $m['agent_developer'] }}</span>@endif</p>
                            <p class="received-machines__body">{{ $m['body'] }}</p>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="received-pending">{{ __('site.ui.no_machine') }}</p>
            @endif
        </div>
    @endif
</section>
@endsection
