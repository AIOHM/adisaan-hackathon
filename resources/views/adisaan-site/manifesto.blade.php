@extends('adisaan-site.layout')
@section('title', __('pages.manifesto.title'))
@push('styles')<link rel="stylesheet" href="/assets/adisaan/site/manifesto-paper.css?v=20261031-paper">@endpush
@section('content')
<article class="paper" aria-labelledby="paper-title">
    <header class="paper-cover">
        <div class="paper-shell">
            <div class="paper-cover__top">
                <span>#TBWIM / The Best Way I Imagine</span>
            </div>
            <div class="paper-cover__grid">
                <div class="paper-cover__title">
                    <p class="paper-kicker">{{ __('pages.manifesto.kicker') }}</p>
                    <h1 id="paper-title">{{ __('pages.manifesto.heading_1') }}<br><em>{{ __('pages.manifesto.heading_2') }}</em>.</h1>
                    <p class="paper-cover__dek">{{ __('pages.manifesto.dek') }}</p>
                </div>
                <div class="paper-cover__margin">
                    <span class="paper-cover__mark" aria-hidden="true">&ldquo;</span>
                    <p>{{ __('pages.manifesto.margin_q_1') }} <em>{{ __('pages.manifesto.margin_q_2') }}</em></p>
                    <span class="paper-cover__margin-note">{{ __('pages.manifesto.margin_note') }}</span>
                </div>
            </div>
            <div class="paper-cover__bottom">
                <span>{{ __('pages.manifesto.working') }}</span>
            </div>
        </div>
    </header>

    <div class="paper-shell paper-layout">
        <nav class="paper-contents" aria-label="{{ __('pages.manifesto.on_page') }}">
            <p class="paper-contents__label">{{ __('pages.manifesto.on_page') }}</p>
            <ol>
                <li><a href="#abstract"><span>00</span> {{ __('pages.manifesto.abstract') }}</a></li>
                <li><a href="#principle"><span>01</span> {{ __('pages.manifesto.principle') }}</a></li>
                <li><a href="#practice"><span>02</span> {{ __('pages.manifesto.practice') }}</a></li>
                <li><a href="#mirror"><span>03</span> {{ __('pages.manifesto.mirror') }}</a></li>
                <li><a href="#intelligence"><span>04</span> {{ __('pages.manifesto.intelligence') }}</a></li>
                <li><a href="#method"><span>05</span> {{ __('pages.manifesto.method') }}</a></li>
                <li><a href="#million"><span>06</span> {{ __('pages.manifesto.million') }}</a></li>
            </ol>
        </nav>

        <div class="paper-body">
            <section class="paper-section paper-abstract" id="abstract" aria-labelledby="abstract-title">
                <p class="paper-section__label"><span>00</span> {{ __('pages.manifesto.abstract') }}</p>
                <h2 id="abstract-title">{{ __('pages.manifesto.abstract_title') }}</h2>
                <p class="paper-lead">{{ __('pages.manifesto.abstract_lead') }}</p>
                <p>{{ __('pages.manifesto.abstract_2') }}</p>
                <p><strong>{{ __('pages.manifesto.abstract_why') }}</strong> {{ __('pages.manifesto.abstract_3') }}</p>
                <p class="paper-abstract__note"><strong>{{ __('pages.manifesto.proposition') }}</strong> {{ __('pages.manifesto.proposition_text') }}</p>
            </section>

            <section class="paper-section" id="principle" aria-labelledby="principle-title">
                <p class="paper-section__label"><span>01</span> {{ __('pages.manifesto.principle') }}</p>
                <h2 id="principle-title">{{ __('pages.manifesto.principle_title') }}</h2>
                <p>{{ __('pages.manifesto.principle_1') }}</p>
                <p>{{ __('pages.manifesto.principle_2') }}</p>
                <blockquote class="paper-pullquote">
                    <p>{{ __('pages.manifesto.pullquote_1') }}<br>{{ __('pages.manifesto.pullquote_2') }}</p>
                    <cite>{{ __('pages.manifesto.pullquote_cite') }}</cite>
                </blockquote>
            </section>

            <section class="paper-section" id="practice" aria-labelledby="practice-title">
                <p class="paper-section__label"><span>02</span> {{ __('pages.manifesto.practice') }}</p>
                <h2 id="practice-title">{{ __('pages.manifesto.practice_title') }}</h2>
                <p>{{ __('pages.manifesto.practice_1') }}</p>
                <ol class="paper-sequence">
                    <li><span>01</span><div><strong>{{ __('pages.manifesto.pause') }}</strong><p>{{ __('pages.manifesto.pause_text') }}</p></div></li>
                    <li><span>02</span><div><strong>{{ __('pages.manifesto.imagine') }}</strong><p>{{ __('pages.manifesto.imagine_text') }}</p></div></li>
                    <li><span>03</span><div><strong>{{ __('pages.manifesto.try') }}</strong><p>{{ __('pages.manifesto.try_text') }}</p></div></li>
                    <li><span>04</span><div><strong>{{ __('pages.manifesto.return') }}</strong><p>{{ __('pages.manifesto.return_text') }}</p></div></li>
                </ol>
                <p>{{ __('pages.manifesto.practice_note') }}</p>
            </section>

            <section class="paper-section" id="mirror" aria-labelledby="mirror-title">
                <p class="paper-section__label"><span>03</span> {{ __('pages.manifesto.mirror') }}</p>
                <h2 id="mirror-title">{{ __('pages.manifesto.mirror_title') }}</h2>
                <p>{{ __('pages.manifesto.mirror_1') }}</p>
                <div class="paper-lenses" aria-label="Four storytelling lenses">
                    <div><span>{{ __('pages.manifesto.night') }}</span><p>{{ __('pages.manifesto.night_text') }}</p></div>
                    <div><span>{{ __('pages.manifesto.dawn') }}</span><p>{{ __('pages.manifesto.dawn_text') }}</p></div>
                    <div><span>{{ __('pages.manifesto.midday') }}</span><p>{{ __('pages.manifesto.midday_text') }}</p></div>
                    <div><span>{{ __('pages.manifesto.twilight') }}</span><p>{{ __('pages.manifesto.twilight_text') }}</p></div>
                </div>
                <p class="paper-small">{{ __('pages.manifesto.mirror_note') }}</p>
            </section>
            <section class="paper-section" id="intelligence" aria-labelledby="intelligence-title">
                <p class="paper-section__label"><span>04</span> {{ __('pages.manifesto.intelligence') }}</p>
                <h2 id="intelligence-title">{{ __('pages.manifesto.intelligence_title') }}</h2>
                <p class="paper-lead">{{ __('pages.manifesto.intelligence_lead') }}</p>
                <p>{{ __('pages.manifesto.intelligence_built') }}</p>
                <h3 style="font-size:clamp(18px,1.8vw,22px);color:var(--paper-ink);margin:24px 0 10px;">{{ __('pages.manifesto.problem_title') }}</h3>
                <p>{{ __('pages.manifesto.problem_1') }}</p>
                <p>{{ __('pages.manifesto.problem_2') }}</p>
                <h3 style="font-size:clamp(18px,1.8vw,22px);color:var(--paper-ink);margin:24px 0 10px;">{{ __('pages.manifesto.solves_title') }}</h3>
                <p>{{ __('pages.manifesto.solves_1') }}</p>
                <p>{{ __('pages.manifesto.solves_2') }}</p>
                <p>{{ __('pages.manifesto.solves_3') }}</p>
                <div class="paper-rule"><span>{{ __('pages.manifesto.rule_human') }}</span><span aria-hidden="true">&ne;</span><span>{{ __('pages.manifesto.rule_synthetic') }}</span></div>
            </section>

            <section class="paper-section" id="method" aria-labelledby="method-title">
                <p class="paper-section__label"><span>05</span> {{ __('pages.manifesto.method') }}</p>
                <h2 id="method-title">{{ __('pages.manifesto.method_title') }}</h2>
                <p>{{ __('pages.manifesto.method_lead') }}</p>
                <dl class="paper-method">
                    <div><dt><span>01</span> {{ __('pages.manifesto.ask') }}</dt><dd>{{ __('pages.manifesto.ask_text') }}</dd></div>
                    <div><dt><span>02</span> {{ __('pages.manifesto.receive') }}</dt><dd>{{ __('pages.manifesto.receive_text') }}</dd></div>
                    <div><dt><span>03</span> {{ __('pages.manifesto.separate') }}</dt><dd>{{ __('pages.manifesto.separate_text') }}</dd></div>
                    <div><dt><span>04</span> {{ __('pages.manifesto.examine') }}</dt><dd>{{ __('pages.manifesto.examine_text') }}</dd></div>
                    <div><dt><span>05</span> {{ __('pages.manifesto.revise') }}</dt><dd>{{ __('pages.manifesto.revise_text') }}</dd></div>
                </dl>
                <p class="paper-small">{{ __('pages.manifesto.method_note_1') }}</p>
                <p class="paper-small">{{ __('pages.manifesto.method_note_2_1') }} <a href="{{ route('adisaan.public.privacy') }}">{{ __('pages.manifesto.method_note_2_link') }}</a> {{ __('pages.manifesto.method_note_2_2') }}</p>
            </section>

            <section class="paper-section" id="million" aria-labelledby="million-title">
                <p class="paper-section__label"><span>06</span> {{ __('pages.manifesto.million') }}</p>
                <h2 id="million-title">{{ __('pages.manifesto.million_title') }}</h2>
                <p>{{ __('pages.manifesto.million_1') }}</p>
                <p>{{ __('pages.manifesto.million_2') }} <strong>{{ __('pages.manifesto.million_no_money') }}</strong></p>
                <p>{{ __('pages.manifesto.million_follow_1') }} <a href="{{ route('adisaan.public.home') }}">{{ __('pages.manifesto.million_follow_link') }}</a>.</p>
            </section>

            <footer class="paper-conclusion" aria-labelledby="conclusion-title">
                <p class="paper-section__label">{{ __('pages.manifesto.invitation') }}</p>
                <h2 id="conclusion-title">{{ __('pages.manifesto.invitation_title') }}</h2>
                <p>{{ __('pages.manifesto.invitation_text') }}</p>
                <a class="paper-action" href="{{ route('adisaan.public.answer', ['story' => $current['id'] ?? null]) }}">{{ __('pages.manifesto.invitation_cta') }} <span aria-hidden="true">↗</span></a>
                <p class="paper-conclusion__note">{{ __('pages.manifesto.invitation_note') }}</p>
            </footer>
        </div>
    </div>
</article>
@endsection
