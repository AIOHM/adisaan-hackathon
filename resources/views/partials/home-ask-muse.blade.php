{{--
    Ask Muse – public AI chat widget (column-friendly, no outer <section>).
    Connects to AmbassadorAgent (public RAG only – no private data).
    Embedded beside FAQ in a 2-column layout.
--}}

@php
    $askMuseEyebrow = $askMuseEyebrow ?? 'AIOHM Ambassador';
    $askMuseHeading = $askMuseHeading ?? 'Ask AIOHM Ambassador';
    $askMuseIntro = $askMuseIntro ?? 'Have a question about AIOHM? Ask AIOHM Ambassador.';
    $askMuseInputLabel = $askMuseInputLabel ?? 'Ask Muse a question';
    $askMusePrivacyUrl = $askMusePrivacyUrl ?? 'https://www.ohm.events/privacy-policy/';
    $askMuseProcessor = $askMuseProcessor ?? 'AIOHM';
    $askMusePoweredBy = $askMusePoweredBy ?? 'Powered by AIOHM Ambassador · Public knowledge only';
    $askMuseShowFloat = $askMuseShowFloat ?? true;
    $askMuseInputPlaceholder = $askMuseInputPlaceholder ?? 'Ask AIOHM Ambassador...';
@endphp

{{-- Ask Muse column content --}}
<div id="ask-muse" class="flex flex-col h-full">
    <p class="text-xs uppercase tracking-[0.25em] text-ohm-attention/80 mb-3 font-medium">{{ $askMuseEyebrow }}</p>
    <h2 class="text-2xl md:text-3xl font-bold text-white font-montserrat mb-2">{{ $askMuseHeading }}</h2>
    <p class="text-ohm-text/50 text-sm mb-6 leading-relaxed">
        {{ $askMuseIntro }}
    </p>

    <div id="muse-public-chat" class="w-full flex-1 flex flex-col">
        {{-- Message area (hidden until first response) --}}
        <div
            id="muse-public-messages"
            class="hidden mb-4 max-h-64 overflow-y-auto rounded-2xl bg-white/[0.04] border border-white/[0.06] backdrop-blur-sm p-4 text-left space-y-3 scroll-smooth"
            role="log" aria-live="polite" aria-label="Chat messages"
        ></div>

        {{-- Input pill --}}
        <form
            id="muse-public-form"
            class="relative flex items-center bg-white/[0.07] backdrop-blur-sm rounded-full border border-white/[0.1] shadow-lg shadow-black/20 transition-all focus-within:border-ohm-attention/40 focus-within:shadow-ohm-attention/10 mt-auto"
            autocomplete="off"
        >
            <label for="muse-public-input" class="sr-only">{{ $askMuseInputLabel }}</label>
            <input
                id="muse-public-input" type="text" name="message" maxlength="500"
                placeholder="{{ $askMuseInputPlaceholder }}" autocomplete="off"
                class="flex-1 bg-transparent border-none text-white placeholder-white/30 text-sm md:text-base px-6 py-4 focus:outline-none focus:ring-0"
            >
            <button id="muse-public-send" type="submit" aria-label="Send message"
                class="flex-shrink-0 mr-2 w-10 h-10 rounded-full flex items-center justify-center transition-all"
                style="background-color: var(--ohm-attention);"
            >
                <svg class="w-5 h-5 text-[#0F240C]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </form>
        <label for="muse-public-consent" class="mt-3 flex items-start gap-2 text-[11px] text-white/75 cursor-pointer">
            <input id="muse-public-consent" type="checkbox" required form="muse-public-form" class="mt-0.5 accent-[#D97706]" aria-label="Consent to processing message data according to Privacy Policy">
            <span>I agree that {{ $askMuseProcessor }} may process my message and technical request data to answer it, as described in the <a href="{{ $askMusePrivacyUrl }}" class="underline text-white hover:text-[#D97706]">Privacy Policy</a>. Do not include sensitive personal data.</span>
        </label>


        <p class="text-[11px] text-white/60 mt-3">{{ $askMusePoweredBy }}</p>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════
     Floating mini-widget — appears when user scrolls past the HERO.
     Typing here scrolls to the Ask Muse column for the response.
     ═══════════════════════════════════════════════════════════════════ --}}
<div
    id="muse-float"
    @if(!$askMuseShowFloat) hidden @endif
    class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[9999] pointer-events-none opacity-0 translate-y-4 transition-all duration-300"
    aria-label="Quick Ask Muse"
>
    <form
        id="muse-float-form"
        class="pointer-events-auto flex items-center gap-1 rounded-full shadow-2xl shadow-black/40 border border-[#D97706]/45 px-2 py-1.5"
        style="background: rgba(15,36,12,0.92); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);"
        autocomplete="off"
    >
        <span class="flex-shrink-0 ml-2 mr-1 opacity-60" aria-hidden="true">
            <svg class="w-4 h-4 text-[#F59E0B]" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 2a1 1 0 01.894.553l1.618 3.28 3.618.526a1 1 0 01.554 1.706l-2.618 2.552.618 3.6a1 1 0 01-1.45 1.054L10 13.347l-3.236 1.702a1 1 0 01-1.45-1.054l.618-3.6L3.314 8.065a1 1 0 01.554-1.706l3.618-.526L9.106 2.553A1 1 0 0110 2z"/>
            </svg>
        </span>
        <label for="muse-float-input" class="sr-only">{{ $askMuseInputLabel }}</label>
        <input id="muse-float-input" type="text" maxlength="500" placeholder="{{ $askMuseInputPlaceholder }}" autocomplete="off"
            class="bg-transparent border-none text-white placeholder-white/30 text-sm w-48 sm:w-64 px-2 py-2 focus:outline-none focus:ring-0"
        >
        <button type="submit" aria-label="Send"
            class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center transition-all hover:brightness-110"
            style="background-color: #F59E0B;"
        >
            <svg class="w-4 h-4 text-[#0F240C]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
    </form>
    <label for="muse-float-consent" class="pointer-events-auto mx-auto mt-1 flex max-w-sm items-start gap-1.5 rounded-xl bg-[#0F240C]/95 px-3 py-1.5 text-[10px] text-white/60 shadow-lg cursor-pointer">
        <input id="muse-float-consent" type="checkbox" required form="muse-float-form" class="mt-0.5 accent-[#D97706]" aria-label="Consent to processing message to receive an answer">
        <span>I agree to processing of my message to receive an answer. <a href="{{ $askMusePrivacyUrl }}" class="underline text-white/80">Privacy Policy</a></span>
    </label>
</div>

{{-- Ask Muse JS --}}
<script src="{{ asset('js/ask-muse.js') }}?v=20261014-2" defer></script>
