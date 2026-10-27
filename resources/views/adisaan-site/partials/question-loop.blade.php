{{-- The question loop (website plan, section 13): the sealed answer and its two unlock conditions.
     Expects $loop (from AdiSaanPublicSiteController::loopState, or null) and $home['loop_words'].
     Flat surface, one tone step from the section, 14px+ text, no emoji, no gradients, no shadows. --}}
@php
    $w = ($home['loop_words'] ?? []) + [
        'title' => 'The sealed answer', 'sealed' => '', 'rule' => '', 'by_followers' => ':target people follow AdiSaan',
        'now' => 'now :now, counted :date', 'to_go' => ':n to go', 'by_time' => 'the clock reaches :date',
        'opened' => 'Question open since :date', 'released_title' => 'Adrian’s answer',
        'released_followers' => 'Released on :date: :target people followed.', 'released_time' => 'Released on :date: the time came.',
        'verify' => '', 'none' => 'No sealed answer for this question yet.',
    ];
    $t = static fn (string $key, array $vars = []): string => strtr($w[$key] ?? '', array_combine(array_map(static fn ($k) => ':'.$k, array_keys($vars)), array_map(static fn ($v) => (string) $v, $vars)));
    $hash = $loop['answer_sha256'] ?? null;
    $shortHash = $hash ? substr($hash, 0, 8).'…'.substr($hash, -8) : null;
@endphp
{{-- No loop on the current story: render nothing. The block appears only once Adrian has sealed an answer (2026-10-20). --}}
@if(!empty($loop))
<div class="qloop" id="loop" aria-labelledby="loop-heading">
@if($loop['state'] === 'released')
    <h3 id="loop-heading" class="qloop__title">{{ $w['released_title'] }}</h3>
    <p class="qloop__question">{{ $loop['question'] }}</p>
    @if(!empty($loop['answer_video']))
    <video class="qloop__video" controls playsinline preload="metadata" src="{{ $loop['answer_video'] }}">Your browser does not support video playback.</video>
    @endif
    <p class="qloop__meta">{{ $loop['unlock_reason'] === 'followers' ? $t('released_followers', ['date' => $loop['released_at'], 'target' => number_format((int) $loop['target'])]) : $t('released_time', ['date' => $loop['released_at']]) }}</p>
    @if($hash)
    <p class="qloop__hash"><span>SHA-256</span> <code>{{ $hash }}</code></p>
    <p class="qloop__meta">{{ $w['verify'] }}</p>
    @endif
@else
    <h3 id="loop-heading" class="qloop__title">{{ $w['title'] }}</h3>
    <p class="qloop__question">{{ $loop['question'] }}</p>
    <p class="qloop__sealed">{{ $w['sealed'] }}</p>
    @if($hash)
    <p class="qloop__hash"><span>SHA-256</span> <code title="{{ $hash }}">{{ $shortHash }}</code></p>
    @endif
    <p class="qloop__rule">{{ $w['rule'] }}</p>
    <ul class="qloop__conditions">
        @if($loop['target'] !== null)
        <li>
            <strong>{{ $t('by_followers', ['target' => number_format((int) $loop['target'])]) }}</strong>
            @if($loop['followers_now'] !== null)
            <span>{{ $t('now', ['now' => number_format((int) $loop['followers_now']), 'date' => $loop['counted_on'] ?? '—']) }}@if($loop['to_go'] !== null) · {{ $t('to_go', ['n' => number_format((int) $loop['to_go'])]) }}@endif</span>
            @endif
        </li>
        @endif
        @if($loop['deadline'])
        <li><strong>{{ $t('by_time', ['date' => $loop['deadline']]) }}</strong></li>
        @endif
    </ul>
    @if($loop['opened_at'])
    <p class="qloop__meta">{{ $t('opened', ['date' => $loop['opened_at']]) }}@if($loop['followers_at_open'] !== null) · {{ number_format((int) $loop['followers_at_open']) }} {{ ($home['count_words']['follow'] ?? 'follow so far') }}@endif</p>
    @endif
@endif
</div>
@endif
