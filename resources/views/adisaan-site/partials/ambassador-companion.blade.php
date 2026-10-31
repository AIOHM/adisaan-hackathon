<aside class="adisaan-companion" aria-label="AdiSaan, powered by Muse Ambassador">
    <section class="adisaan-native-chat" id="adisaan-native-chat" role="dialog" aria-labelledby="adisaan-chat-title" hidden>
        <button class="adisaan-chat-close" type="button" data-close-ambassador aria-label="Close conversation">×</button>
        <div class="adisaan-chat-heading">
            <div class="adisaan-chat-heading__copy">
                <h2 id="adisaan-chat-title">Ask AdiSaan</h2>
                <p>Your online partner</p>
            </div>
        </div>
        @include('partials.home-ask-muse', [
            'askMuseEyebrow' => '',
            'askMuseHeading' => '',
            'askMuseIntro' => '',
            'askMuseInputLabel' => 'What do you want to know?',
            'askMuseInputPlaceholder' => 'What do you want to know?',
            'askMusePrivacyUrl' => '/privacy',
            'askMuseProcessor' => 'AdiSaan’s AIOHM workspace and its AI provider',
            'askMusePoweredBy' => "Powered by M.U.S.E. Ambassador\nApproved public knowledge",
            'askMuseShowFloat' => false,
        ])
    </section>
    {{-- Open on the home page, closed everywhere else.

         It used to open itself on every load: 220px of speech parked over
         whatever sat in the bottom right corner, which on /journey was one
         of the three ask cards. A greeting is right the first time someone
         arrives and wrong on top of the thing they came to read.

         Keyed on the canonical path rather than $active, so a greeting
         follows the page even when the shared key changes.

         Hidden in the markup rather than by script, so on the pages that
         start closed it is never visible for the moment before JavaScript
         runs. Everything that deliberately raises it — the sanctuary gate
         guide, the end of a story video, closing the chat — sets
         hidden = false itself and is unaffected. --}}
    @php $companionGreets = ($canonicalPath ?? '') === '/'; @endphp
    <div class="adisaan-thought" data-avatar-thought @unless($companionGreets) hidden @endunless>
        <button type="button" data-hide-thought aria-label="Hide AdiSaan’s thoughts">×</button>
        <span data-thought-context>Let’s talk</span>
        <p data-journey-speech>I imagined a million. What could we discover together along the way?</p>
        <button type="button" data-open-ambassador>Tell me what you think ↗</button>
    </div>
    <button class="adisaan-companion-face" type="button" data-open-ambassador aria-label="Ask AdiSaan, powered by Muse Ambassador" aria-controls="adisaan-native-chat" aria-expanded="false"><img src="/assets/adisaan/site/avatar-mascot.jpg" alt="AdiSaan" width="84" height="84"><span aria-hidden="true"></span></button>
</aside>
