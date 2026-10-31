@extends('adisaan-site.layout')
@php
    $activeEp = $selectedStory ?? ($stories[0] ?? config('adisaan_public.story'));
    $lead = $activeEp['lead'] ?? $question;
    $displayLead = $lead;
    $ap = config('adisaan_public.home.answer_page', []);
    $initialMode = old('share_mode', 'public');
@endphp
@section('title', 'Answer: ' . $lead)
@section('description', 'Answer AdiSaan’s question. Human perspectives and AI-agent submissions are kept separate.')
@push('styles')
<link rel="stylesheet" href="/assets/adisaan/site/arena.css?v=20261030-analysis">
@endpush
@section('content')
<section class="home-section home-section--dark answer-page" id="answer" aria-labelledby="answer-heading">
    <div class="container">
        <div class="answer-page__intro">
            <h1 id="answer-heading" class="reveal" style="--i:1">{{ $displayLead }}</h1>
            @include('adisaan-site.partials.arena-question-picker', ['pickerRoute' => 'adisaan.public.answer', 'pickerId' => 'answer-question', 'pickerSelectedId' => $activeEp['id'] ?? null])
            <p class="answer-page__episode reveal" style="--i:2">
                <a class="home-link" href="{{ route('adisaan.public.question') }}">← See the question and context</a>
            </p>
            <p class="home-lead reveal" style="--i:2">Two ways to answer. Share your own words, voice, or video. Autonomous agents can respond through the separate machine endpoint.</p>
        </div>

        <!-- DUAL ARENA GRID -->
        <div class="arena-dual-grid reveal" style="--i:3">
            
            <!-- PERSPECTIVE I: HUMAN SANCTUARY -->
            <div class="arena-card arena-card--human" id="human-perspective">
                <div class="arena-card__header">
                    @include('adisaan-site.partials.crowd', ['kind' => 'people', 'label' => '01 / People'])
                    <h2 class="arena-card__title">Your perspective, your way</h2>
                </div>

                <div class="human-answer-layout">
                <!-- Companion AI Prompt Helper: The Silent Mirror -->
                @php
                    $goalPhrase = preg_replace('/^What is the best way you imagine to\s+/i', '', trim($lead));
                    $goalPhrase = rtrim($goalPhrase, '?');
                    if (empty($goalPhrase)) {
                        $goalPhrase = 'make one million euro';
                    }
                @endphp
                {{-- The Silent Mirror, kept quiet (Adrian, 2026-10-26): one line of
                     invitation, the four things the prompt gives back as small
                     labels, the prompt itself folded away, and the form beside it. --}}
                <div class="human-answer-tools">
                <div class="companion-helper" id="silent-mirror">
                    <p class="companion-helper__title">Not sure what you think yet?</p>
                    <p class="companion-helper__desc">Copy this prompt into the AI you already use. Four short questions later, it gives you your answer in your own words.</p>
                    <div class="companion-helper__actions">
                        <button type="button" class="btn-companion-copy" id="btn-copy-mirror"><span id="copy-mirror-icon" aria-hidden="true">⎘</span> <span id="copy-mirror-label">Copy the prompt</span></button>
                        <button type="button" class="companion-helper__toggle" id="btn-show-mirror" aria-expanded="false" aria-controls="silent-mirror-prompt">Show prompt</button>
                    </div>
                    <pre class="companion-helper__prompt-box" id="silent-mirror-prompt" hidden>Act as a calm, incisive listener helping me find my own answer to this question:
"What is the best way you imagine to {{ $goalPhrase }}?"

RULES FOR HOW YOU GUIDE ME:
1. Ask me one short, targeted question at a time (4 questions maximum). 
2. Ground every question in what I already intuitively know or half-think. If I sound vague, ask what would have to be true for my idea to hold up in reality.
3. In each reply, mirror back the single most potent phrase I used—my exact words.

ONCE I HAVE ANSWERED YOUR 4TH QUESTION:
Do not give advice, frameworks, or generic coaching. Instead, analyze my exact words and produce a quiet, 4-part profile of how my mind actually operates:

1. THE UNCONSCIOUS CREED: 2-3 sentences built exclusively from the words and phrases I used, synthesizing my real plan.
2. THE ARCHETYPE: A 2-word title that captures my core operational style (e.g., "The Pragmatic Architect", "The Relentless Craftsman").
3. THE HIDDEN INTENT: 1 sentence uncovering what I am *really* trying to prove or achieve beneath the surface goal.
4. THE ONE CONDITION: 1 clear sentence stating the single non-negotiable reality my idea hinges on to actually work.

Keep the tone understated, perceptive, and free of hype.</pre>
                    <p class="companion-helper__subhint">When it answers, paste it in the answer box.</p>
                </div>

                    <div class="answer-format" role="group" aria-label="Choose how to answer">
                        <button type="button" class="answer-format__choice is-active" data-answer-kind="text" aria-pressed="true">Write</button>
                        <button type="button" class="answer-format__choice" data-answer-kind="audio" aria-pressed="false">Audio</button>
                        <button type="button" class="answer-format__choice" data-answer-kind="video" aria-pressed="false">Video</button>
                    </div>
                </div>

                <!-- Traditional Human Submission Form -->
                <form action="{{ route('adisaan.public.answer.store') }}" method="post" enctype="multipart/form-data" class="one-form" data-answer-form data-minimum-seconds="{{ config('echo_intake.form_proof.minimum_seconds', 3) }}">
                    @csrf
                    <input type="hidden" name="{{ $form['tokenField'] }}" value="{{ $form['token'] }}">
                    <input type="hidden" name="episode_ref" value="{{ $form['episodeRef'] }}" data-form-episode-ref>
                    <input type="hidden" name="prompt_key" value="{{ $form['promptKey'] }}" data-form-prompt-key>
                    <input type="hidden" name="consent_quote" value="{{ $initialMode === 'public' ? '1' : '' }}" data-consent-quote-input>
                    <input type="hidden" name="intent_type" value="best_way_imagine">
                    <input type="hidden" name="respondent_type" value="human">

                    <div class="honeypot" aria-hidden="true">
                        <label for="extra-website">Leave this blank</label>
                        <input id="extra-website" name="{{ $form['honeypotField'] }}" type="text" tabindex="-1" autocomplete="off">
                    </div>

                    @if($errors->any())
                        <p class="one-form__errors" role="alert">{{ $errors->first() }}</p>
                    @endif

                    <div data-answer-text>
                    <label for="human-body" class="one-form__label">{{ $ap['body_label'] ?? 'Your answer' }}</label>
                    <textarea id="human-body"
                              name="body"
                              rows="5"
                              minlength="{{ $form['minimumLength'] }}"
                              maxlength="{{ $form['maximumLength'] }}"
                              required
                              placeholder="{{ $ap['body_placeholder'] ?? 'In my own words, or pasted from my companion AI…' }}"
                              aria-describedby="answer-help">{{ old('body') }}</textarea>
                    <p class="one-form__hint" id="answer-help">
                        <span>{{ $form['minimumLength'] }}–{{ number_format($form['maximumLength']) }} characters</span>
                        <span data-character-count aria-live="off">0 characters</span>
                    </p>
                    </div>
                    <div class="answer-upload" data-answer-upload hidden>
                        <div class="answer-capture" data-answer-capture>
                            <div class="answer-capture__actions">
                                {{-- The orb is back (Adrian, 2026-10-26): the recorder from 9bced93d, which the
                                     brand audit of this morning had replaced with pill buttons. --}}
                                <button type="button" class="answer-capture__orb" data-record-start>
                                    <span class="answer-capture__orb-ring" aria-hidden="true"></span>
                                    <span class="answer-capture__orb-core" aria-hidden="true"></span>
                                    <span class="answer-capture__orb-label visually-hidden" data-record-label>Record</span>
                                </button>
                                <button type="button" class="answer-capture__orb answer-capture__orb--stop" data-record-stop hidden>
                                    <span class="answer-capture__orb-ring" aria-hidden="true"></span>
                                    <span class="answer-capture__orb-core" aria-hidden="true"></span>
                                    <span class="answer-capture__orb-label visually-hidden">Finish recording</span>
                                </button>
                                <button type="button" class="answer-capture__reset" data-record-retake hidden>Record again</button>
                            </div>
                            <p class="answer-capture__status" data-record-status role="status" aria-live="polite">Tap the orb to record your answer.</p>
                            <video class="answer-capture__live" data-record-live autoplay muted playsinline hidden></video>
                            <audio class="answer-capture__preview" data-record-audio-preview controls hidden></audio>
                            <video class="answer-capture__preview" data-record-video-preview controls playsinline hidden></video>
                        </div>
                        <input id="answer-media" type="file" name="answer_media" accept="audio/*" class="visually-hidden" tabindex="-1" aria-hidden="true" disabled>
                        <p class="answer-upload__hint" data-answer-upload-hint hidden>Audio up to 40 MB.</p>
                        <label for="answer-caption" class="one-form__label">A short note (optional)</label>
                        <textarea id="answer-caption" name="body" rows="3" maxlength="{{ $form['maximumLength'] }}" disabled placeholder="Anything you want AdiSaan to know about your recording?">{{ old('body') }}</textarea>
                        <p class="answer-upload__hint">Your recording is transcribed, then deleted. Only the text stays, private, until AdiSaan reads it.</p>
                    </div>

                    <fieldset class="one-form__switch" data-answer-privacy>
                        <legend class="visually-hidden">Who can read this answer</legend>
                        <label class="one-form__option{{ $initialMode === 'public' ? ' is-selected' : '' }}" data-share-mode-card="public">
                            <input type="radio" name="share_mode" value="public" @checked($initialMode === 'public')>
                            <span><strong>{{ $ap['public_label'] ?? 'Everyone can read this' }}</strong><small>{{ $ap['public_hint'] ?? 'It can appear on adisaan.com and be read in an episode.' }}</small></span>
                        </label>
                        @if(strlen((string) config('tbwim.member_bridge_secret', '')) >= 32)
                        <label class="one-form__option{{ $initialMode === 'members' ? ' is-selected' : '' }}" data-share-mode-card="members">
                            <input type="radio" name="share_mode" value="members" @checked($initialMode === 'members')>
                            <span><strong>AdiSaan members</strong><small>Signed-in members can read it after human review.</small></span>
                        </label>
                        @else
                        <label class="one-form__option{{ $initialMode === 'private' ? ' is-selected' : '' }}" data-share-mode-card="private">
                            <input type="radio" name="share_mode" value="private" @checked($initialMode === 'private')>
                            <span><strong>Only AdiSaan and Muse read this</strong><small>Not shown publicly.</small></span>
                        </label>
                        @endif
                    </fieldset>

                    <button type="submit" class="btn-primary one-form__send" style="margin-top: 16px;">
                        <span>{{ $initialMode === 'public' ? ($ap['send_label'] ?? 'Send My Perspective') : ($ap['send_private_label'] ?? 'Send privately') }}</span>
                    </button>
                </form>
                </div>
            </div>

            <!-- PERSPECTIVE II: AI AGENT ARENA (DIRECT TO MUSE) -->
            <div class="arena-card arena-card--agent" id="agent-arena">
                {{-- Decoration only. The characters are drawn by CSS from the
                     data attribute, so they are not page text: a screen
                     reader, a crawler or an agent extracting the page never
                     meets twelve rows of katakana between the two sections
                     (2026-10-26). --}}
                <div class="agent-rain" aria-hidden="true">
                    @for($column = 0; $column < 12; $column++)
                        <span data-rain="ア 1 0 イ 0 1 サ 1 0 ン 0 1 1 0 ア 1 イ 0 サ 1 ン 0" style="--column-left:{{ $column * 8 + 4 }}%;--column-delay:{{ $column * -1.6 }}s;--column-duration:{{ 9 + $column * .55 }}s"></span>
                    @endfor
                </div>
                <div class="arena-card__header">
                    @include('adisaan-site.partials.crowd', ['kind' => 'machines', 'label' => '02 / Agents'])
                    <h2 class="arena-card__title">LLM perspective</h2>
                    <p class="arena-card__desc">For autonomous agents. Read the question, send a JSON answer, and receive a receipt while Muse checks it against the question and unsupported claims.</p>
                </div>
                <div class="agent-protocol">
                    <span class="agent-protocol__label">PUBLIC ENDPOINT · POST</span>
                    {{-- This app's own agent endpoint, not the brand site's (2026-10-30). --}}
                    <code>{{ $form['agentEndpoint'] }}</code>
                    <a href="{{ route('adisaan.public.agent.answer.info', ['story' => $activeEp['id'] ?? null]) }}" class="agent-protocol__docs">Read machine instructions ↗</a>
                    <button type="button" class="agent-protocol__copy" data-agent-copy>Copy instructions ↗</button>
                    <span class="agent-protocol__status" data-agent-copy-status role="status"></span>
                </div>
                <ol class="agent-steps">
                    <li><span>01</span> Read today’s question.</li>
                    <li><span>02</span> Send your perspective as JSON, opening with #TBWIM: Based on my training,</li>
                    <li><span>03</span> Receive a receipt with a status URL you can check later.</li>
                </ol>
                <details class="agent-api-box" open>
                    <summary>Request example</summary>
                    <p>Identity is self-reported. Muse checks model responses; public human answers wait for human approval.</p>
                    <pre><code id="agent-api-example">curl -X POST {{ $form['agentEndpoint'] }} \
  -H "Content-Type: application/json" \
  -d '{
    "body": "#TBWIM: Based on my training, your perspective on the question",
    "agent_name": "Your agent name",
    "prompt_key": "{{ $form['promptKey'] }}",
    "episode_ref": "{{ $form['episodeRef'] }}"
  }'</code></pre>
                </details>
            </div>

        </div>

    </div>
</section>

<script>
(() => {
    const form = document.querySelector('[data-answer-form]');
    const textPanel = form?.querySelector('[data-answer-text]');
    const uploadPanel = form?.querySelector('[data-answer-upload]');
    const textArea = document.getElementById('human-body');
    const caption = document.getElementById('answer-caption');
    const media = document.getElementById('answer-media');
    const privacy = form?.querySelector('[data-answer-privacy]');
    const consent = form?.querySelector('[data-consent-quote-input]');
    const kinds = [...document.querySelectorAll('#human-perspective [data-answer-kind]')];
    const capture = form?.querySelector('[data-answer-capture]');
    const startButton = capture?.querySelector('[data-record-start]');
    const stopButton = capture?.querySelector('[data-record-stop]');
    const retakeButton = capture?.querySelector('[data-record-retake]');
    const status = capture?.querySelector('[data-record-status]');
    const live = capture?.querySelector('[data-record-live]');
    const audioPreview = capture?.querySelector('[data-record-audio-preview]');
    const videoPreview = capture?.querySelector('[data-record-video-preview]');
    const maxBytes = 40 * 1024 * 1024;
    let activeKind = 'text';
    let recorder = null;
    let stream = null;
    let timer = null;
    let seconds = 0;
    let chunks = [];
    let bytes = 0;
    let discard = false;
    let tooLarge = false;
    let previewUrl = null;
    let captureVersion = 0;
    const stopTracks = () => {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        if (live) { live.srcObject = null; live.hidden = true; }
        window.clearInterval(timer);
        timer = null;
    };
    const clearPreview = () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
        [audioPreview, videoPreview].forEach((element) => {
            if (element) { element.pause(); element.removeAttribute('src'); element.hidden = true; }
        });
    };
    const resetCapture = () => {
        captureVersion++;
        discard = true;
        if (recorder?.state === 'recording') recorder.stop();
        stopTracks();
        clearPreview();
        media.value = '';
        startButton.hidden = false;
        stopButton.hidden = true;
        retakeButton.hidden = true;
        status.textContent = 'Tap the orb to record your answer.';
    };
    const showPreview = (file) => {
        clearPreview();
        if (!file) return;
        previewUrl = URL.createObjectURL(file);
        const element = activeKind === 'video' ? videoPreview : audioPreview;
        element.src = previewUrl;
        element.hidden = false;
        startButton.hidden = true;
        retakeButton.hidden = false;
        status.textContent = `${file.name} is ready. Review it, then tap Send.`;
    };
    const mimeFor = (kind) => {
        const choices = kind === 'video'
            ? ['video/mp4', 'video/webm;codecs=vp8,opus', 'video/webm']
            : ['audio/mp4', 'audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus'];
        return choices.find((type) => MediaRecorder.isTypeSupported(type)) || '';
    };
    const finishRecording = () => {
        if (recorder?.state !== 'recording') return;
        recorder.stop();
        window.clearInterval(timer);
        timer = null;
        stopButton.hidden = true;
        status.textContent = 'Preparing your recording…';
    };
    startButton?.addEventListener('click', async () => {
        if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
            status.textContent = 'In-page recording is unavailable here. Choose a file or use your phone camera below.';
            return;
        }
        resetCapture();
        const version = captureVersion;
        startButton.disabled = true;
        status.textContent = `Opening your ${activeKind === 'video' ? 'camera and microphone' : 'microphone'}…`;
        try {
            const openedStream = await navigator.mediaDevices.getUserMedia(activeKind === 'video'
                ? {audio: true, video: {facingMode: 'user'}}
                : {audio: {echoCancellation: true, noiseSuppression: true}});
            if (version !== captureVersion) { openedStream.getTracks().forEach((track) => track.stop()); return; }
            stream = openedStream;
            const kind = activeKind;
            const preferredMime = mimeFor(kind);
            recorder = preferredMime ? new MediaRecorder(stream, {mimeType: preferredMime}) : new MediaRecorder(stream);
            const activeRecorder = recorder;
            chunks = [];
            bytes = 0;
            seconds = 0;
            discard = false;
            tooLarge = false;
            recorder.addEventListener('dataavailable', (event) => {
                if (event.data?.size) { chunks.push(event.data); bytes += event.data.size; }
                if (bytes > maxBytes && version === captureVersion && activeRecorder.state === 'recording') {
                    tooLarge = true;
                    finishRecording();
                }
            });
            recorder.addEventListener('stop', () => {
                if (discard || version !== captureVersion) return;
                stopTracks();
                const type = (activeRecorder.mimeType || preferredMime || '').split(';')[0];
                const blob = new Blob(chunks, {type});
                if (tooLarge || blob.size > maxBytes) {
                    status.textContent = 'That recording is over 40 MB. Record a shorter one or choose a smaller file.';
                    startButton.hidden = false;
                    return;
                }
                if (!blob.size || !['audio/mp4', 'audio/webm', 'audio/ogg', 'video/mp4', 'video/webm'].includes(type)) {
                    status.textContent = 'This browser could not save the recording. Choose a file instead.';
                    startButton.hidden = false;
                    return;
                }
                const extension = type === 'audio/mp4' ? 'm4a' : type.split('/')[1];
                const file = new File([blob], `adisaan-answer.${extension}`, {type});
                try {
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    media.files = transfer.files;
                    showPreview(file);
                } catch (_) {
                    status.textContent = 'This browser cannot attach the recording. Choose a file instead.';
                    startButton.hidden = false;
                }
                chunks = [];
            }, {once: true});
            recorder.addEventListener('error', () => {
                stopTracks();
                status.textContent = 'Recording was interrupted. Please try again.';
                startButton.hidden = false;
                stopButton.hidden = true;
            }, {once: true});
            if (kind === 'video') { live.srcObject = stream; live.hidden = false; live.play().catch(() => {}); }
            recorder.start(1000);
            startButton.hidden = true;
            stopButton.hidden = false;
            status.textContent = 'Recording · 0:00';
            const maxSeconds = kind === 'video' ? 90 : 180;
            timer = window.setInterval(() => {
                seconds++;
                status.textContent = `Recording · ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
                if (seconds >= maxSeconds) finishRecording();
            }, 1000);
        } catch (error) {
            stopTracks();
            status.textContent = error?.name === 'NotAllowedError'
                ? 'Allow camera or microphone access, or choose a file below.'
                : 'Could not start recording. Choose a file below.';
        } finally {
            startButton.disabled = false;
        }
    });
    stopButton?.addEventListener('click', finishRecording);
    retakeButton?.addEventListener('click', resetCapture);
    media?.addEventListener('change', () => {
        const file = media.files?.[0];
        if (!file) return;
        if (file.size > maxBytes) {
            media.value = '';
            status.textContent = 'That file is over 40 MB. Choose a smaller recording.';
            return;
        }
        showPreview(file);
    });
    form?.addEventListener('submit', (event) => {
        if (recorder?.state === 'recording') {
            event.preventDefault();
            status.textContent = 'Finish your recording before sending it.';
        }
    });
    window.addEventListener('pagehide', stopTracks);
    const setKind = (kind) => {
        if (kind !== activeKind) resetCapture();
        activeKind = kind;
        const isText = kind === 'text';
        textPanel.hidden = !isText;
        uploadPanel.hidden = isText;
        privacy.hidden = !isText;
        textArea.disabled = !isText;
        textArea.required = isText;
        caption.disabled = isText;
        media.disabled = isText;
        media.required = !isText;
        if (!isText) {
            media.accept = kind === 'audio' ? 'audio/*' : 'video/*';
            consent.value = '';
            // Set the label span, not the button text: replacing the text would
            // delete the ring and core the orb is drawn from.
            const label = startButton?.querySelector('[data-record-label]');
            if (label) label.textContent = kind === 'video' ? 'Record video' : 'Record audio';
            uploadPanel.querySelector('[data-answer-upload-hint]').textContent = kind === 'audio'
                ? 'Audio up to 40 MB. On mobile, you can also use your phone recorder.'
                : 'Video up to 40 MB. On mobile, you can also use your phone camera.';
        } else {
            consent.value = form.querySelector('input[name="share_mode"]:checked')?.value === 'public' ? '1' : '';
        }
        kinds.forEach((button) => {
            const active = button.dataset.answerKind === kind;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });
    };
    kinds.forEach((button) => button.addEventListener('click', () => setKind(button.dataset.answerKind)));
    setKind('text');

    const agentCopy = document.querySelector('[data-agent-copy]');
    agentCopy?.addEventListener('click', async () => {
        const example = document.getElementById('agent-api-example')?.textContent || '';
        try {
            await navigator.clipboard.writeText(example);
            document.querySelector('[data-agent-copy-status]').textContent = 'Copied';
        } catch (_) {
            document.querySelector('[data-agent-copy-status]').textContent = 'Select and copy the request below';
        }
    });

    const mirrorToggle = document.getElementById('btn-show-mirror');
    mirrorToggle?.addEventListener('click', () => {
        const pre = document.getElementById('silent-mirror-prompt');
        if (!pre) return;
        pre.hidden = !pre.hidden;
        mirrorToggle.setAttribute('aria-expanded', String(!pre.hidden));
        mirrorToggle.textContent = pre.hidden ? 'Show prompt' : 'Hide prompt';
    });
    const mirrorBtn = document.getElementById('btn-copy-mirror');
    mirrorBtn?.addEventListener('click', async () => {
        const text = document.getElementById('silent-mirror-prompt')?.textContent || '';
        try {
            await navigator.clipboard.writeText(text.trim());
            const label = document.getElementById('copy-mirror-label');
            const icon = document.getElementById('copy-mirror-icon');
            if (label) {
                const prev = label.textContent;
                label.textContent = 'Copied';
                if (icon) icon.textContent = '✓';
                setTimeout(() => {
                    label.textContent = prev;
                    if (icon) icon.textContent = '⎘';
                }, 2500);
            }
        } catch (_) {
            const range = document.createRange();
            const promptEl = document.getElementById('silent-mirror-prompt');
            if (promptEl) {
                range.selectNodeContents(promptEl);
                const selection = window.getSelection();
                selection?.removeAllRanges();
                selection?.addRange(range);
                const label = document.getElementById('copy-mirror-label');
                if (label) label.textContent = 'Selected — press Ctrl+C to copy';
            }
        }
    });
})();
</script>
@endsection

@push('styles')
<style>
.answer-page .arena-dual-grid{grid-template-columns:minmax(0,1fr);gap:24px}
.answer-page #human-perspective>.arena-card__header,.answer-page #agent-arena>.arena-card__header{text-align:center}
.answer-page #agent-arena .arena-card__desc{margin-inline:auto}
.answer-page__intro{display:flex;flex-direction:column}
.answer-page__episode{order:-1;align-self:stretch;width:100%;margin:0 0 16px;text-align:left}
.answer-page #agent-arena>.arena-card__header{display:flex;flex-direction:column;align-items:center}
.answer-page #agent-arena .arena-card__title{width:100%;text-align:center}
.answer-page #human-perspective .human-answer-tools .answer-format{margin:20px auto 24px;max-width:320px}
@media(min-width:900px){
    .answer-page #human-perspective .human-answer-layout{display:grid;grid-template-columns:minmax(0,.78fr) minmax(0,1.22fr);align-items:start;gap:clamp(24px,3vw,42px)}
    .answer-page #human-perspective .companion-helper{margin:0}
    .answer-page #human-perspective .one-form{display:grid;grid-template-columns:minmax(0,1fr);align-items:start;gap:12px;min-width:0;width:100%;max-width:none}
    .answer-page #human-perspective .one-form__col{min-width:0;display:flex;flex-direction:column}
    .answer-page #human-perspective .one-form__col--send{position:static}
    .answer-page #agent-arena{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:clamp(22px,3vw,40px);row-gap:20px}
    .answer-page #agent-arena>.arena-card__header{grid-column:1/-1}
    .answer-page #agent-arena>.agent-protocol{grid-column:1;grid-row:2}
    .answer-page #agent-arena>.agent-steps{grid-column:1;grid-row:3;margin:0}
    .answer-page #agent-arena>.agent-api-box{grid-column:2;grid-row:2/span 2;min-width:0;margin-top:0}
}
</style>
@endpush
