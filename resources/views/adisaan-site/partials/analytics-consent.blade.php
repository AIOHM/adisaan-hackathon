<aside class="adisaan-analytics-choice" aria-label="{{ __('site.ui.analytics_aria') }}" hidden data-analytics-choice-panel>
    <p class="adisaan-analytics-choice__label">{{ __('site.ui.analytics_label') }}</p>
    <p>{{ __('site.ui.analytics_text') }}</p>
    <div class="adisaan-analytics-choice__actions">
        <button type="button" data-analytics-choice="allow">{{ __('site.ui.allow_analytics') }}</button>
        <button type="button" data-analytics-choice="deny">{{ __('site.ui.deny_analytics') }}</button>
    </div>
    <a href="{{ route('adisaan.public.privacy') }}">{{ __('site.ui.how_works') }}</a>
</aside>
