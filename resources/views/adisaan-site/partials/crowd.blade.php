{{--
    A small crowd, drawn rather than photographed.

    Five round tokens overlapping into a huddle, which reads as "a group" at a
    glance where a word does not. Used twice on the answer page: people on the
    left card, models on the right.

    The people are generated fictional portraits, not submitted answers or
    depictions of actual visitors.

    The machine icons are service logos for illustration. Their appearance
    does not imply sponsorship or that every service has submitted an answer.

    @param string $kind   'people' or 'machines'
    @param string $label  the line under the huddle
--}}
@php
    /**
     * Five people, drawn from the same three shapes in different colours.
     *
     * The tones do vary, deliberately: a crowd that is one colour is not a
     * crowd. They are a spread, not portraits of anyone, and the five sit in
     * no order that means anything — the first is simply first in the array.
     *
     * Each also gets its own ground, because at 44px two figures in the same
     * colours read as the same figure twice.
     */
    $people = [
        ['name' => 'Fictional person icon 1', 'image' => 'person-1.webp'],
        ['name' => 'Fictional person icon 2', 'image' => 'person-2.webp'],
        ['name' => 'Fictional person icon 3', 'image' => 'person-3.webp'],
        ['name' => 'Fictional person icon 4', 'image' => 'person-4.webp'],
        ['name' => 'Fictional person icon 5', 'image' => 'person-5.webp'],
    ];

    /** Illustrative service marks; they do not imply a submitted answer. */
    $machines = [
        ['name' => 'Meta Muse', 'image' => 'muse.svg'],
        ['name' => 'ChatGPT by OpenAI', 'image' => 'openai.svg'],
        ['name' => 'Google Gemini', 'image' => 'gemini.svg'],
        ['name' => 'Moonshot Kimi', 'image' => 'kimi.svg'],
        ['name' => 'Claude by Anthropic', 'image' => 'claude.svg'],
    ];

    $isPeople = ($kind ?? 'people') === 'people';
    $members = $isPeople ? $people : $machines;
@endphp

<div class="crowd crowd--{{ $isPeople ? 'people' : 'machines' }}">
    <ul class="crowd__ring" role="img" aria-label="{{ $label ?? ($isPeople ? 'People answering' : 'Models answering') }}">
        @foreach($members as $i => $member)
            <li class="crowd__token{{ $isPeople ? ' crowd__token--portrait' : ' crowd__token--logo' }}" style="--i:{{ $i }}" title="{{ $member['name'] }}">
                <img src="/assets/adisaan/site/{{ $isPeople ? 'people/' : 'ai-logos/' }}{{ $member['image'] }}" alt="" aria-hidden="true" loading="lazy" decoding="async">
            </li>
        @endforeach
        @if($isPeople)<li class="crowd__token crowd__token--more" style="--i:5"><span>+</span></li>@endif
    </ul>
    @if($showLabel ?? true)
        <p class="crowd__label">{{ $label ?? ($isPeople ? 'People imagining answers' : 'AI agent services') }}</p>
    @endif
</div>
