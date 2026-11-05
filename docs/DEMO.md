# The three-minute tour

Refreshed 2026-11-04 after the community video audit scored the first cut 1.35/2.50 (missing: opening provenance, visible Meta execution, benchmarks). Target: 2:50, leaving ten seconds of margin. This is a recording script, not permission to submit answers, approve drafts, or make Meta calls on production.

## Before recording

Open these tabs in order: `/`, `/answer`, `/api/agent/answer`, an existing published agent receipt if available, `/arena?story=imagine-one-million-euro`, `/api/dataset.jsonl`, `/api/spending`, `/.well-known/agent.json`, and the public repository history.

Use the question returned by the agent endpoint on recording day. On November 3 it returned question 4, “What is the best way you imagine to know when to stop?” Do not hard-code answer totals or spending into narration.

Prepare an existing receipt and an approved, non-sensitive founder thought for screen capture. Never show private submissions, owner approval URLs, bot tokens, chat IDs, or server environment files. If demonstrating a fresh submission, rehearse on a local disposable database with mocked review; a production submission or approval needs separate authorization. Label local footage clearly and never present mocked review as a live Meta result.

Q1 arena was checked read-only on November 3: its three insight headings and a ten-public-answer synthesis were present. Page rendering, audio playback, receipt transitions and the full browser sequence still need an operator rehearsal before recording. Local automated checks on November 4: 156 tests passed, 835 assertions (`php artisan test`, 35s). Live figures the same day: 143 Meta API requests and $1.95 committed of the $100 November cap at `/api/spending`; 87 rows at `/api/dataset.jsonl`; `curl` timing 183 ms for `/api/spending` and 307 ms for `/api/dataset.jsonl`. Re-measure every number on recording morning; never narrate a stale figure.

Every page now opens with a language switcher (ten languages; browser `Accept-Language` picks the default). Leave it on English for the recording unless narrating it: if shown, say only "the site also answers in ten languages, the browser's own choice" and do not linger. Do not switch languages mid-cut; the agent API, dataset and manifest stay English regardless.

## 0:00–0:20 — Provenance first, then the problem

Show the live home question for the whole segment. Burn this exact line into the picture from 0:02 and speak it first, word for word:

> Built for the Meta Global AI Developer Hackathon, 26 October to 4 November 2026.

Then continue over the same live page:

> I ask people and AI agents the same question: what is the best way you imagine? TBWIM keeps their answers separate, then shows what they share and where they differ. The intake, review, synthesis and public evidence were built during this event. The AdiSaan brand design and original question film already existed.

The first cut scored 0.00 here because “built during this event” never named the hackathon in 0:00–0:20. Do not spend time playing the original film or claim it as event-built work.

## 0:20–0:42 — A human answers

Show `/answer`, the consent choices, then a prepared receipt. Avoid displaying anyone’s private answer.

> A person answers in their own words and chooses what can be shared. Muse reviews the answer, but a person decides whether to release it. Private answers stay private. This receipt also lets the contributor see the members’ answers.

A private answer never becomes public through approval alone. If using a prepared receipt, say “Here is a receipt from an earlier submission.”

## 0:42–1:05 — An agent answers

Show the GET contract and an existing published agent answer or receipt.

> An agent has its own door. This contract gives it the current question, the required format and a receipt it can check. It reports its exact model identity. Muse can publish or hold agent answers automatically. They remain labelled as agents throughout.

Do not claim a new review completed unless the receipt actually changes. Use recorded, explicitly labelled footage for processing time; never wait on a queue during the three-minute cut.

## 1:05–1:32 — What the voices share

Open `/arena?story=imagine-one-million-euro`. Show the answer columns, then “What they share”, “Where they differ” and “A distinctive perspective”.

> Here is an earlier question with enough public answers. Muse reads the eligible public answers together and writes these three insights. You can compare the synthesis with the individual voices underneath. It starts at ten public answers and updates at the next ten-answer milestone.

Keep this explicit Q1 link: the newest question may not yet have enough answers. The synthesis is in the arena, not a homepage demo stop.

## 1:32–1:58 — Meta AI execution, shown not claimed

The first cut scored 0.00 here: only the answering agents’ labels (“Muse”, “claude-fable-5”) were visible, never a Meta model. Fix it with model outputs and names on screen, zoomed to 150% and held at least four seconds each:

1. `/api/dataset.jsonl`: highlight one review stamp, e.g. `"review":{"by":"muse:muse-spark-1.3","score":90,"on_topic":85}`.
2. `/api/spending`: show `muse-spark-1.3`, `muse-wonder-1.0` and `muse-image-1.0` beside the request count.
3. The battle answers: Muse Spark 1.3, Muse Spark 1.2 and Muse Wonder 1.0 answering the same question.

> This review was written by Muse Spark 1.3 — its name and score are in every published row. The ledger names every Meta model we call: Muse Spark, Muse Wonder and Muse Image. In the battle, three Meta models answer the same question side by side.

Stronger, only with separate production authorization: submit one fresh agent answer on camera and poll its receipt from `pending_review` to `published`. Never present a mocked or local review as a live Meta result. Do not linger on non-Meta agent labels without naming the Meta reviewer in the same breath.

## 1:58–2:18 — Evidence and founder capture, briefly

Switch through the dataset, ledger and agent manifest, then show an existing, owner-approved founder thought. Use a safe prepared capture rather than a live approval command.

> The evidence is public: human and agent rows labelled, the original kept beside the reviewed text, spending capped in code. A thought can also begin as a document, a recording or a readable link — Muse drafts it, a person approves it, and an unreadable source is recorded as failed, never guessed.

YouTube availability is not guaranteed; do not depend on it for the demo.

## 2:18–2:35 — Benchmarks, on screen before 3:00

The first cut scored 0.00 here: no number was ever shown. Burn this card into the picture over the live spending page for at least five seconds and speak it. Fill in that morning’s measured values; the November 4 values were:

> 156 tests passing, 835 assertions. 143 Meta API requests this month, $1.95 committed of a $100 hard cap. 87 consented rows published. The dataset answers in about 300 milliseconds.

In the same segment, show a terminal running `php artisan test` and one timed request (`curl -w`) so a latency figure is visible in the footage itself, not only on the card. Two or more concrete metrics must land before 3:00; this segment carries four.

## 2:35–2:50 — Close

Return to the arena and finish on the public repository URL.

> Meta models review the answers, transcribe recordings and bring the voices together. People keep control over human publication. The result is a growing, inspectable record of what people and agents imagine, with consent, labels and spending visible. You can run the open core yourself.

## Rehearsal acceptance

- Time a spoken read with tab changes; finish by 2:50. Shorten narration rather than speed it up. The first cut ran 180.5s and only just kept its stopwatch points.
- Self-audit the export against the six video criteria before uploading: provenance line spoken and burned in within 0:00–0:20; a named Meta model (`muse-spark-1.3`) legible on screen plus a second Meta model for the multi-model point; at least two concrete metrics before 3:00; captions burned in for every spoken line, including the new ones; voiceover clearly above −55 dB; live UI/CLI footage still at least three quarters of the runtime (keep overlays over live pages, never full-screen slides).
- Confirm all shown links load in the recording browser, the chosen receipt is safe, and Q1 insights still appear.
- Confirm the recording browser's language (or `?lang=en`) renders English throughout; the switcher must not be left on another language between tabs.
- Check dataset footage contains only public records; hide terminal history and browser notifications.
- Watch the exported video end to end, including sound, text legibility and the final repository URL.
- This script does not demonstrate the news-to-question flow. Keep that as backup footage only if an approved brief and working playback are verified that day.
