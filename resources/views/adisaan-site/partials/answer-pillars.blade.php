{{--
    Two pillars (Adrian, 2026-10-26): people in green, machines in gold,
    standing on one plinth, each as tall as its share of the larger count.
    Drawn as an SVG with shaded sides and lit tops, so it reads as solid
    without any 3D transform inside the turning card, where a nested
    transform would fight the flip. Replaces the leaf tree on every card.

    @param array $count   ['people' => int, 'machines' => int]
    @param bool  $compact true on the deck's small cards
--}}
@php
    $count = ($count ?? []) + ['people' => 0, 'machines' => 0];
    $people = max(0, (int) $count['people']);
    $machines = max(0, (int) $count['machines']);
    $top = max($people, $machines, 1);
    // An empty side keeps a thin disc, so "0" is a pillar that has not
    // started, not a hole in the drawing.
    $heightOf = static fn (int $n): float => 10 + 96 * $n / $top;
    $pillars = [
        ['kind' => 'people', 'cx' => 58, 'n' => $people, 'h' => $heightOf($people), 'label' => 'people'],
        ['kind' => 'machines', 'cx' => 122, 'n' => $machines, 'h' => $heightOf($machines), 'label' => 'machines'],
    ];
    $uid = substr(md5(uniqid('', true)), 0, 6);
@endphp
<svg class="qcard__pillars {{ !empty($compact) ? 'qcard__pillars--compact' : '' }}" viewBox="0 0 180 174" role="img" aria-label="{{ number_format($people) }} answers from people and {{ number_format($machines) }} from machines" focusable="false">
    <defs>
        <linearGradient id="pp-{{ $uid }}" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#2d6a3b"/><stop offset=".45" stop-color="#5fae72"/><stop offset="1" stop-color="#245a31"/></linearGradient>
        <linearGradient id="pm-{{ $uid }}" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#9c7a2b"/><stop offset=".45" stop-color="#ecd08a"/><stop offset="1" stop-color="#8a6a22"/></linearGradient>
    </defs>
    <ellipse class="qcard__plinth" cx="90" cy="153" rx="76" ry="14"/>
    <ellipse class="qcard__plinth-rim" cx="90" cy="153" rx="76" ry="14"/>
    @foreach($pillars as $i => $p)
        @php $y1 = 150 - $p['h']; $x0 = $p['cx'] - 22; $x1 = $p['cx'] + 22; @endphp
        <g class="qcard__pillar qcard__pillar--{{ $p['kind'] }}" style="--i:{{ $i }}">
            <path class="qcard__pillar-side" fill="url(#{{ $p['kind'] === 'people' ? 'pp' : 'pm' }}-{{ $uid }})" d="M{{ $x0 }} {{ $y1 }} V150 A22 7 0 0 0 {{ $x1 }} 150 V{{ $y1 }} A22 7 0 0 1 {{ $x0 }} {{ $y1 }} Z"/>
            <ellipse class="qcard__pillar-top" cx="{{ $p['cx'] }}" cy="{{ $y1 }}" rx="22" ry="7"/>
        </g>
        <text class="qcard__pillar-num qcard__pillar-num--{{ $p['kind'] }}" style="--i:{{ $i }}" x="{{ $p['cx'] }}" y="{{ $y1 - 13 }}" text-anchor="middle">{{ number_format($p['n']) }}</text>
    @endforeach
</svg>
