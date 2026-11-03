@extends('adisaan-site.layout')
@section('title', __('pages.privacy.title'))
@section('content')
<article class="section container prose">
    <p class="eyebrow">{{ __('pages.privacy.eyebrow') }}</p>
    <h1>{{ __('pages.privacy.heading') }}</h1>
    <p>{{ __('pages.privacy.intro') }}</p>

    <h2>{{ __('pages.privacy.answers_heading') }}</h2>
    <p>{{ __('pages.privacy.answers_1') }}</p>
    <p>{{ __('pages.privacy.answers_2_1') }} <strong>{{ __('pages.privacy.answers_2_not') }}</strong> {{ __('pages.privacy.answers_2_2') }}</p>

    <h2>{{ __('pages.privacy.people_heading') }}</h2>
    <p>{{ __('pages.privacy.people_1') }}</p>

    <h2>{{ __('pages.privacy.optional_heading') }}</h2>
    <p>{{ __('pages.privacy.optional_1') }}</p>

    <h2>{{ __('pages.privacy.technical_heading') }}</h2>
    <p>{{ __('pages.privacy.technical_1') }}</p>

    <h2>{{ __('pages.privacy.rights_heading') }}</h2>
    <p>{{ __('pages.privacy.rights_1_1') }} <a href="mailto:contact@adisaan.com">contact@adisaan.com</a> {{ __('pages.privacy.rights_1_2') }}</p>

    <p class="fine-print">{{ __('pages.privacy.updated') }}</p>
</article>
@endsection
