<form class="arena-picker" action="{{ route($pickerRoute ?? 'adisaan.public.arena') }}" method="get">
    <label for="{{ $pickerId ?? 'arena-question' }}">What is the best way you imagine to...</label>
    <div class="arena-picker__controls">
        <div class="arena-picker__select-wrap">
            <select id="{{ $pickerId ?? 'arena-question' }}" name="story">
                @foreach($stories ?? [] as $story)
                    @php $optionText = preg_replace('/^what is the best way you imagine to\s+/iu', '', $story['question']); @endphp
                    <option value="{{ $story['id'] }}" @selected(($pickerSelectedId ?? ($homeEpisode['id'] ?? null)) === $story['id'])>{{ $optionText }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit">Choose</button>
    </div>
</form>
