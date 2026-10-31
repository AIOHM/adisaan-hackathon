{{--
    Key for the living map the layout draws behind the page.

    Extracted from home.blade.php on 2026-10-24 when /million gained the same
    atlas. One copy, so the two pages cannot drift into describing the same
    dots differently.

    Needs $livingMap (from loadLivingMap()).

    Copy comes from $home when the including page has it, because /ro replaces
    $home with the Romanian mirror — reading config here instead would quietly
    serve English on the Romanian page. Pages without $home fall back to config.
--}}
@php($mapCopy = $copy ?? ($home['living_map'] ?? config('adisaan_public.home.living_map', [])))
<div class="home-map-key" aria-label="{{ $mapCopy['label'] ?? 'Map activity and answer locations' }}">
    <div class="home-map-key__groups">
        <div class="home-map-key__group">
            <span class="home-map-key__symbol home-map-key__symbol--activity" aria-hidden="true"></span>
            <div><strong>{{ $mapCopy['activity'] ?? 'Site activity · last 7 days' }}</strong>
                @if($livingMap['visitors'])
                    <span>{{ collect($livingMap['visitors'])->map(fn ($point) => $point['name'].' '.number_format($point['count']))->implode(' · ') }}</span>
                @else
                    <span>{{ $mapCopy['empty_activity'] ?? '' }}</span>
                @endif
            </div>
        </div>
        <div class="home-map-key__group">
            <span class="home-map-key__symbol home-map-key__symbol--answer" aria-hidden="true"></span>
            <div><strong>{{ $mapCopy['answers'] ?? 'Answers with a place' }}</strong>
                @if($livingMap['answers'])
                    <span>{{ collect($livingMap['answers'])->map(fn ($point) => $point['name'].' '.number_format($point['count']))->implode(' · ') }}</span>
                @else
                    <span>{{ $mapCopy['empty_answers'] ?? '' }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
