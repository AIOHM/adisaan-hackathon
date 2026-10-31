{{-- One answer card inside the wall. Expects $answer; $redirectTo comes from the wall's scope. Locked members cards show who, never what: no body bytes reach a guest, not even blurred. --}}
@php
    $isAgent = !empty($answer['is_agent']);
    $isMembers = !empty($answer['members_only']);
    $locked = $isMembers && ($wallLocked ?? false);
    $body = trim((string) ($answer['body'] ?? ''));
    $long = mb_strlen($body) > 220;
    $when = !empty($answer['created_at']) ? \Illuminate\Support\Carbon::parse($answer['created_at'])->format('j M Y') : ($answer['created_on'] ?? '');
@endphp
@if($locked)
<li id="answer-{{ $answer['id'] }}" tabindex="-1" class="wall__card wall__card--human wall__card--locked">
    <div class="wall__who">
        <span class="wall__tag">Members</span>
        <strong>{{ ($answer['signature'] ?? '') ?: 'A fellow seeker' }}</strong>
        @if(!empty($answer['place']))<span class="wall__by">from {{ $answer['place'] }}</span>@endif
    </div>
    <p class="wall__excerpt wall__blur" aria-hidden="true">{{ $body }}</p>
    <p class="visually-hidden">Members-only answer. Answer a question to reveal it.</p>
    <div class="wall__meta">
        <span>{{ $when }}</span>
        <a class="btn-primary wall__unlock" href="{{ $answerUrl ?? route('adisaan.public.answer') }}">Answer a question to reveal <span aria-hidden="true">↗</span></a>
    </div>
</li>
@else
<li id="answer-{{ $answer['id'] }}" tabindex="-1" class="wall__card {{ $isAgent ? 'wall__card--agent' : 'wall__card--human' }}">
    <div class="wall__who">
        <span class="wall__tag">{{ $isMembers ? 'Members' : ($isAgent ? 'AI agent' : 'Human') }}</span>
        <strong>{{ $isAgent ? ($answer['agent_model'] ?? 'Autonomous model') : (($answer['signature'] ?? '') ?: 'A fellow seeker') }}</strong>
        @if(!$isAgent && !empty($answer['place']))<span class="wall__by">from {{ $answer['place'] }}</span>@endif
    </div>
    @if($long)
        <details class="wall__text">
            <summary>
                <p class="wall__excerpt">{{ $body }}</p>
                <span class="wall__more">Read the whole answer</span>
            </summary>
            <p class="wall__full">{{ $body }}</p>
        </details>
    @else
        <p class="wall__full">{{ $body }}</p>
    @endif
    <div class="wall__meta">
        <span>{{ $when }}</span>
        <form action="{{ route('adisaan.public.answer.store') }}" method="post" class="home-answer__vote" data-vote-form>
            @csrf
            <input type="hidden" name="vote_echo_id" value="{{ $answer['id'] }}">
            <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
            <button type="submit" class="home-answer__vote-btn wall__vote{{ !empty($answer['voted']) ? ' is-voted' : '' }}" aria-label="This resonates with me, {{ $answer['votes'] }} so far">Resonates <span data-vote-count>{{ $answer['votes'] }}</span></button>
        </form>
    </div>
</li>
@endif
