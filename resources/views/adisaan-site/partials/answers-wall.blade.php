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
    $membersOnly = $answers->where('members_only', true)->count();
@endphp
<div class="wall" id="{{ $wallId }}">
    <div class="wall__bar">
        @if($membersOnly > 0)
            <p class="wall__count">{{ __('site.ui.showing_answers', ['total' => number_format($answers->count()), 'people' => number_format($people->count()), 'members' => number_format($membersOnly), 'machines' => number_format($machines->count())]) }}</p>
        @else
            <p class="wall__count">{{ __('site.ui.showing_public', ['total' => number_format($answers->count()), 'people' => number_format($people->count()), 'machines' => number_format($machines->count())]) }}</p>
        @endif
    </div>
    @if($answers->isEmpty())
        <div class="wall__card wall__empty"><p class="wall__full">{{ __('site.ui.wall_empty') }}</p>@if(!empty($answerUrl))<a href="{{ $answerUrl }}">{{ __('site.ui.wall_add') }}</a>@endif</div>
    @else
        <div class="wall__duel">
            @foreach([['key' => 'people', 'title' => __('site.ui.from_people'), 'rows' => $people, 'empty' => __('site.ui.no_people')], ['key' => 'agents', 'title' => __('site.ui.from_agents'), 'rows' => $machines, 'empty' => __('site.ui.no_agents')]] as $column)
                <section class="wall__column wall__column--{{ $column['key'] }}" aria-label="{{ $column['title'] }}">
                    <h3 class="wall__column-title">{{ $column['title'] }} <span>{{ number_format($column['rows']->count()) }}</span></h3>
                    <ol class="wall__grid">
                        @forelse($column['rows'] as $answer)
                            @include('adisaan-site.partials.answers-wall-card', ['answer' => $answer, 'wallExtra' => $loop->index >= 5])
                        @empty
                            <li class="wall__card wall__empty"><p class="wall__full">{{ $column['empty'] }}</p></li>
                        @endforelse
                    </ol>

                </section>
            @endforeach
        </div>
        @php($hiddenTotal = $people->count() > 5 ? $people->count() - 5 : 0)
        @php($hiddenTotal += $machines->count() > 5 ? $machines->count() - 5 : 0)
        @if($hiddenTotal > 0)
            <button type="button" class="wall__more-btn" data-wall-more aria-expanded="false" data-more="{{ __('site.ui.show_more', ['n' => $hiddenTotal]) }}" data-less="{{ __('site.ui.show_less') }}">{{ __('site.ui.show_more', ['n' => $hiddenTotal]) }} <span aria-hidden="true">↓</span></button>
        @endif
    @endif
</div>
