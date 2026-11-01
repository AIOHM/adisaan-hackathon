#!/usr/bin/env bash
# Give an approved signal question AdiSaan's voice, end to end:
#   1. fetch the brief text from the server,
#   2. speak it locally with F5-TTS (AdiSaan clone),
#   3. upload the mp3 and attach it (tbwim:signal-audio).
#
# Usage: ops/brief-voice/make-brief.sh <signal-question-id>
#
# One-time setup lives in README.md next to this script. Override the
# reference recording with BRIEF_REF=/path/to/adrian.m4a if needed.
set -euo pipefail

ID="${1:?usage: make-brief.sh <signal-question-id>}"
HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$HERE/../.." && pwd)"
WORKSPACE="$(cd "$ROOT/.." && pwd)"
SERVER="adisaan-vps"
APP="/var/www/tbwim.adisaan.com"
REF="${BRIEF_REF:-$WORKSPACE/AdiSaan/personal/voice - need transcribe/Who i am.m4a}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "== brief text for signal question #$ID"
ssh "$SERVER" "cd $APP && php artisan tinker --execute=\"echo App\\\\Models\\\\SignalQuestion::findOrFail($ID)->brief_script;\"" > "$TMP/brief.txt"
test -s "$TMP/brief.txt"

echo "== speaking it (F5-TTS, reference: $REF)"
"$HERE/.venv/bin/python" "$HERE/make_brief.py" \
    --text-file "$TMP/brief.txt" \
    --ref "$REF" \
    --out "$TMP/brief.mp3"

echo "== attaching as the brief's voice"
scp -q "$TMP/brief.mp3" "$SERVER:/tmp/brief-$ID.mp3"
ssh "$SERVER" "cd $APP && php artisan tbwim:signal-audio $ID /tmp/brief-$ID.mp3"

echo "Done. The brief on /news now plays AdiSaan's voice."
