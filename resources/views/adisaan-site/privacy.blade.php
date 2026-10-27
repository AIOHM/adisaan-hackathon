@extends('adisaan-site.layout')
@section('title', 'Privacy and AI Transparency — AdiSaan')
@section('content')
<article class="section container prose">
    <p class="eyebrow">PRIVACY &amp; AI TRANSPARENCY</p>
    <h1>What happens to your answer.</h1>
    <p>AdiSaan is an animated documentary project, hosted on the AIOHM platform. Its videos use an animated avatar and an AI-assisted voice. The public Muse companion is an AI assistant, not a person speaking live.</p>

    <h2>Answers to the question</h2>
    <p>The answer form stores the words you submit, the question reference, your public or private choice, and technical data needed to protect the form from abuse. A public answer may be displayed on the site and discussed in an episode. A private answer is kept out of the public answer list and is available to AdiSaan and Muse in his workspace for review. Please do not include sensitive personal information about yourself or someone else in an answer.</p>
    <p>For an audio or video answer, Muse uses the configured Whisper transcription service to create text for private review. After a successful transcription, the original recording is deleted. If transcription fails, the recording stays in private storage so it can be retried. The transcript remains private for review and is not published. The present answer form does <strong>not</strong> ask you to opt in to training Muse. Sending an answer does not grant that permission. Other forms may offer a separate Muse-training choice; read their terms before submitting. Study analysis, product improvement and model training are different activities.</p>

    <h2>People and AI agents</h2>
    <p>Human answers and AI-agent submissions are labeled separately. An agent’s model or developer name is supplied by the submitter; it is not verified by AdiSaan. An automated score must not be treated as a scientific measure of a response or a person’s beliefs.</p>

    <h2>Optional details and email</h2>
    <p>If the form asks for a name, country, city or age band, those details are optional. A voluntarily supplied place can appear with a public answer and in country counts; an age band is used only in aggregate. We do not require a declaration of faith or worldview. On the answer form, your email is optional and is kept only if you choose follow-up for a reply and member access. Study participation does not require agreeing to marketing emails.</p>

    <h2>Technical data</h2>
    <p>The form uses a hashed IP address, request fingerprint, time, user-agent and question reference to detect abuse. Essential session cookies support form security and duplicate-vote protection. Google Analytics receives limited cookie-free measurement signals by default. If you choose “Allow analytics”, it can use optional Analytics cookies to measure visits and story interactions more accurately. Advertising personalisation and Google signals remain off. Your choice is saved in this browser; use “Analytics choice” in the footer to change it. The choice is logged with its time, page, IP address and browser information. AdiSaan counts followers from dated social-platform measurements, not from Analytics visits or clicks.</p>

    <h2>Your choices and rights</h2>
    <p>You may ask to access, correct or delete information about your answer, or withdraw a consent you gave. Write to <a href="mailto:contact@adisaan.com">contact@adisaan.com</a> with enough detail to locate the submission. Withdrawing consent does not undo lawful processing that happened before withdrawal. A public quotation already shared outside this site may not be retrievable from every third-party copy.</p>

    <p class="fine-print">Updated 26 October 2026. This page describes the current public answer flow; a fuller study notice and any future donation terms will be published before those features launch.</p>
</article>
@endsection
