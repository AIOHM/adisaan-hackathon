# Brief voice — AdiSaan speaks the brief (F5-TTS, local)

The /news brief plays a real recording in AdiSaan's voice. The server has
no GPU and Meta's API has no speech synthesis, so the voice is generated
here, on Adrian's machine, with the open-source **F5-TTS** zero-shot
cloner — the same engine family as the earlier local drafts — and the
finished mp3 is attached to the question. Nothing is generated per
visitor; the site serves a static file.

## One-time setup

```bash
cd ops/brief-voice
uv venv
uv pip install f5-tts
```

Needs `ffmpeg` on PATH (`brew install ffmpeg`). The first run downloads
the F5-TTS model (a few hundred MB) and, when no reference transcript is
given, its transcription model. Check the F5-TTS model licence before any
commercial use beyond this project.

Reference recording: by default the script clones from
`AdiSaan/personal/voice - need transcribe/Who i am.m4a` (clean, unmixed
Adrian). Do **not** clone from the HeyGen render soundtrack — it carries
video mix processing (see `AdiSaan/Adisaan avatar/content/brand/voice-profile.md`).
Override with `BRIEF_REF=/path/to/recording.m4a`.

## Daily use

```bash
# on the server, a draft exists and is approved first:
php artisan tbwim:signal-question
php artisan tbwim:signal-approve <id>

# here, one command does fetch → speak → upload → attach:
ops/brief-voice/make-brief.sh <id>
```

Then open https://tbwim.adisaan.com/news — Play the brief is AdiSaan's
voice, captioned by sentence. Without an attached file the brief is a
silent captioned read; the browser's robot voice is gone entirely.

## Pieces

- `make_brief.py` — text + reference → mp3 (F5-TTS `F5TTS_v1_Base`,
  reference trimmed to 12s; `--ref-text` to give the exact transcript).
- `make-brief.sh` — the one-command loop above.
- Server side: `tbwim:signal-audio <id> <file>` copies the audio to
  `public/assets/adisaan/site/briefs/` and records it on the question
  (`--clear` removes it).

First-run check: generate one brief, listen end to end, and compare tone
and pacing with the 2026-10-11 reference before attaching it for real.
