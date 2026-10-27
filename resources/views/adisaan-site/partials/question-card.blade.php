{{--
    The tarot card: the one the deck on /question deals.

    Front — a gold rule inset from the edge with a mark in each corner, the
    numeral above, the question holding the middle, the life area below left,
    the OHM seal where a tarot deck puts its maker's mark.

    Back — what the clearing has heard: people against machines on this
    question as two pillars, and how far the whole site has climbed
    towards the million.

    The classes are the deck's, so both read the same rules in
    question-answer-countdown.css. If that card changes, change it here, or
    better, make question.blade.php use this partial too.

    @param array  $story     the story being dealt (lead/question, life_area, id)
    @param string $numeral   roman numeral for its place in the deck
    @param array  $areas     config('adisaan_public.life_areas')
    @param array  $count     ['people' => int, 'machines' => int, 'total' => int]
    @param int    $siteTotal answers across every question
    @param int    $goal      the million
    @param string $modifier  extra class, e.g. 'qcard--wide'
--}}
@php
    $areaKey = $story['life_area'] ?? null;
    $area = $areaKey ? ($areas[$areaKey] ?? null) : null;
    $cardModifier = $modifier ?? '';
    $count = ($count ?? []) + ['people' => 0, 'machines' => 0, 'total' => 0];
    $goal = max(1, (int) ($goal ?? 1000000));
    $siteTotal = (int) ($siteTotal ?? 0);
    // A true millionth of a percent draws nothing, which reads as broken
    // rather than early. Floor the bar, keep the real numbers in the label.
    $pct = $siteTotal / $goal * 100;
    $drawn = $pct > 0 ? max(1.2, $pct) : 0;
@endphp
<div class="qcard {{ $cardModifier }}" data-qcard>
    <div class="qcard__flip">
        <div class="qcard__face qcard__face--front">
            <span class="qcard__numeral" aria-hidden="true">{{ $numeral }}</span>
            <h3 class="qcard__question">{{ $story['lead'] ?? $story['question'] ?? '' }}</h3>
            <span class="qcard__turn">{{ $area['label'] ?? 'Any area' }} · turn the card</span>
            <img class="qcard__seal" src="/assets/adisaan/site/ohm-mark.svg" alt="" width="38" height="40" loading="lazy" aria-hidden="true">
        </div>

        <div class="qcard__face qcard__face--back">
            <p class="qcard__back-title">{{ $story['badge'] ?? 'Question' }}</p>

            <div class="qcard__back-main">
                <div class="qcard__back-copy">
                    <div class="qcard__score">
                        <span class="qcard__score-side"><strong>{{ number_format($count['people']) }}</strong><small>people</small></span>
                        <span class="qcard__score-vs" aria-hidden="true">vs</span>
                        <span class="qcard__score-side qcard__score-side--machine"><strong>{{ number_format($count['machines']) }}</strong><small>machines</small></span>
                    </div>

                    <div class="qcard__climb">
                        <span class="qcard__climb-bar" role="img" aria-label="{{ number_format($siteTotal) }} of {{ number_format($goal) }} answers across every question"><i style="width:{{ $drawn }}%"></i></span>
                        <span class="qcard__climb-meta">{{ number_format($count['total']) }} received for this question &middot; {{ number_format($siteTotal) }} of {{ number_format($goal) }} together</span>
                    </div>
                </div>
                @include('adisaan-site.partials.answer-pillars', ['count' => $count])
            </div>

            <div class="qcard__actions">
                <a href="{{ $story['answer_url'] ?? route('adisaan.public.answer', ['story' => $story['id'] ?? null]) }}">Answer <span aria-hidden="true">↗</span></a>
            </div>
        </div>
    </div>
</div>
