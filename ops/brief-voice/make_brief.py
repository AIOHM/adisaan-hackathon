#!/usr/bin/env python3
"""Speak a TBWIM brief in AdiSaan's voice with F5-TTS (zero-shot clone).

Reads the brief text, clones the voice from a short reference recording of
Adrian, and writes an mp3 ready for `tbwim:signal-audio`.

Reference choice matters: use a clean, unmixed recording of Adrian
(e.g. `AdiSaan/personal/voice - need transcribe/Who i am.m4a`), never the
HeyGen render soundtrack (it carries video mix processing — see the brand
voice profile). The reference is trimmed to the first seconds; if no
transcript is given, F5-TTS transcribes the clip itself.

Setup and usage: see README.md in this folder.
"""

from __future__ import annotations

import argparse
import subprocess
import sys
import tempfile
from pathlib import Path


def trim_reference(ref: Path, seconds: float, workdir: Path) -> Path:
    """Cut the reference to its first seconds as wav (ffmpeg)."""
    out = workdir / "reference.wav"
    cmd = [
        "ffmpeg", "-y", "-hide_banner", "-loglevel", "error",
        "-i", str(ref), "-t", str(seconds), "-ac", "1", "-ar", "24000", str(out),
    ]
    subprocess.run(cmd, check=True)
    return out


def to_mp3(wav: Path, mp3: Path) -> None:
    subprocess.run(
        ["ffmpeg", "-y", "-hide_banner", "-loglevel", "error", "-i", str(wav),
         "-codec:a", "libmp3lame", "-b:a", "128k", str(mp3)],
        check=True,
    )


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--text-file", required=True, help="File containing the brief script (plain text).")
    parser.add_argument("--ref", required=True, help="Reference recording of Adrian (m4a/wav/mp3).")
    parser.add_argument("--ref-text", default="", help="Exact transcript of the reference clip. Empty: F5-TTS transcribes it.")
    parser.add_argument("--ref-seconds", type=float, default=12.0, help="Seconds of reference to clone from (default 12).")
    parser.add_argument("--speed", type=float, default=1.15, help="Speech speed multiplier (default 1.15, AdiSaan's preferred pace).")
    parser.add_argument("--out", required=True, help="Output mp3 path.")
    args = parser.parse_args()

    text = Path(args.text_file).read_text(encoding="utf-8").strip()
    if not text:
        print("Brief text is empty.", file=sys.stderr)
        return 1

    try:
        from f5_tts.api import F5TTS
    except ImportError:
        print("f5-tts is not installed here. Setup (once):\n"
              "  cd ops/brief-voice && uv venv && uv pip install f5-tts\n"
              "then run this script with .venv/bin/python.", file=sys.stderr)
        return 1

    with tempfile.TemporaryDirectory() as tmp:
        workdir = Path(tmp)
        ref_wav = trim_reference(Path(args.ref), args.ref_seconds, workdir)
        out_wav = workdir / "brief.wav"
        f5 = F5TTS(model="F5TTS_v1_Base")
        f5.infer(
            ref_file=str(ref_wav),
            ref_text=args.ref_text,
            gen_text=text,
            speed=args.speed,
            file_wave=str(out_wav),
        )
        to_mp3(out_wav, Path(args.out))

    print(f"Wrote {args.out}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
