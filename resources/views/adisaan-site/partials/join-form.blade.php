{{-- The "one email when a new episode is out" form on /about. Words come
     from config('adisaan_public.home.join'); posts to POST / (lead). --}}
@php($join = $home['join'] ?? [])
<section class="home-section home-section--light home-join" id="join" aria-labelledby="join-heading">
    <div class="container home-join__grid reveal">
        <div class="home-join__content">
            <div class="home-join__heading">
                <span class="home-join__mail" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" focusable="false"><rect x="5" y="11" width="38" height="27" rx="5" stroke="currentColor" stroke-width="2"/><path d="m7 14 17 13 17-13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <h2 id="join-heading">{{ $join['title'] ?? 'One email when a new episode is out.' }}</h2>
            </div>
            @if(!empty($join['line']))<p class="home-lead">{{ $join['line'] }}</p>@endif
            <form action="{{ route('adisaan.public.construction.lead') }}" method="post" class="home-join__form">
                @csrf
                <label for="join-email" class="home-join__label visually-hidden">{{ $join['label'] ?? 'Your email' }}</label>
                <div class="home-join__row">
                    <input id="join-email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@example.com">
                    <button type="submit" class="btn-primary">{{ $join['button'] ?? 'Subscribe' }}</button>
                </div>
                <label class="checkbox home-join__consent">
                    <input type="checkbox" name="consent_updates" value="1" required @checked(old('consent_updates'))>
                    <span>{{ $join['consent'] ?? '' }} <a href="{{ route('adisaan.public.privacy') }}">{{ $join['privacy_label'] ?? 'Privacy' }}</a>.</span>
                </label>
                @if(session('lead_success'))
                    <p class="home-status" role="status" data-analytics-event="email_joined">{{ session('lead_success') }}</p>
                @endif
                @if($errors->any())
                    <p class="home-error" role="alert">{{ $errors->first() }}</p>
                @endif
            </form>
        </div>
        <div class="home-join__conversation" data-companion-join-dock aria-label="Talk with AdiSaan"></div>
    </div>
</section>
