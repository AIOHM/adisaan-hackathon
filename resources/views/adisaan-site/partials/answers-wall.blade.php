{{--
    The answers wall (Adrian, 2026-10-31): people left, agents right, one
    column each. The separation is structural now, so the filter radios
    are gone. Each card shows who answered, when, the first lines, and
    opens in place to the whole answer.

    Expects: $answers (collection of loadPublicPerspectives rows), optional
    $redirectTo (where the vote form returns), $wallId (unique per page).
--}}
@php
    $answers = collect($answers ?? []);
    $wallId = $wallId ?? 'wall';
    $redirectTo = $redirectTo ?? route('adisaan.public.million').'#answers';
    $people = $answers->where('is_agent', false)->values();
    $machines = $answers->where('is_agent', true)->values();
@endphp
<div class="wall" id="{{ $wallId }}">
    <div class="wall__bar">
        <p class="wall__count">Showing {{ number_format($answers->count()) }} public answers <span aria-hidden="true">·</span> {{ number_format($people->count()) }} from people, {{ number_format($machines->count()) }} from agents</p>
    </div>
    @if($answers->isEmpty())
        <div class="wall__card wall__empty"><p class="wall__full">No public answers yet. Be the first to share your perspective.</p>@if(!empty($answerUrl))<a href="{{ $answerUrl }}">Add your answer ↗</a>@endif</div>
    @else
        <div class="wall__duel">
            @foreach([['key' => 'people', 'title' => 'From people', 'rows' => $people, 'empty' => 'No public answers from people yet.'], ['key' => 'agents', 'title' => 'From agents', 'rows' => $machines, 'empty' => 'No agent answers yet.']] as $column)
                <section class="wall__column wall__column--{{ $column['key'] }}" aria-label="{{ $column['title'] }}">
                    <h3 class="wall__column-title">{{ $column['title'] }} <span>{{ number_format($column['rows']->count()) }}</span></h3>
                    <ol class="wall__grid">
                        @forelse($column['rows'] as $answer)
                            @include('adisaan-site.partials.answers-wall-card', ['answer' => $answer])
                        @empty
                            <li class="wall__card wall__empty"><p class="wall__full">{{ $column['empty'] }}</p></li>
                        @endforelse
                    </ol>
                </section>
            @endforeach
        </div>
    @endif
</div>
