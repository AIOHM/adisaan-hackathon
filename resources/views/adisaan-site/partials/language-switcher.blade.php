{{-- Browser detection with an explicit, shareable override. Native details keeps it keyboard accessible without JavaScript. --}}
<style>
.language-switcher{position:relative;display:inline-block;flex:0 0 auto;font-size:13px}
.language-switcher>summary{cursor:pointer;list-style:none;min-height:42px;display:flex;align-items:center;gap:8px;padding:0 14px;border:1px solid #d6b25e66;border-radius:999px;color:#f4f0e8;background:#0b3d11}
.language-switcher>summary::-webkit-details-marker{display:none}
.language-switcher>summary:focus-visible{outline:3px solid #e9c775;outline-offset:3px}
.language-switcher__choices{position:absolute;z-index:100;right:0;top:calc(100% + 8px);width:260px;max-width:calc(100vw - 32px);padding:10px;display:grid;grid-template-columns:1fr 1fr;gap:4px;background:#123519;border:1px solid #d6b25e99;border-radius:12px;box-shadow:0 12px 30px #0006}
.tabs .language-switcher__link,.language-switcher__current{display:flex;align-items:center;min-height:44px;padding:8px 10px;border:0;border-radius:6px;box-shadow:none;background:transparent;color:#f4f0e8;font-size:13px;line-height:1.25;white-space:normal}
.tabs .language-switcher__link:hover{background:#24552c;box-shadow:none;transform:none}
.language-switcher__current{background:#e9c775;color:#073209;font-weight:700}
</style>
<details class="language-switcher">
    <summary aria-label="{{ __('site.ui.language') }}: {{ $locales[$locale]['native'] ?? 'English' }}"><span aria-hidden="true">◎</span> {{ $locales[$locale]['native'] ?? 'English' }} <span aria-hidden="true">▾</span></summary>
    <div class="language-switcher__choices" aria-label="{{ __('site.ui.language') }}">
    @foreach(($locales ?? []) as $code => $meta)
        @if($code === ($locale ?? 'en'))
            <span class="language-switcher__current" aria-current="true" lang="{{ $code }}">{{ $meta['native'] }}</span>
        @else
            <a class="language-switcher__link" lang="{{ $code }}" hreflang="{{ $code }}" href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}">{{ $meta['native'] }}</a>
        @endif
    @endforeach
    </div>
</details>
