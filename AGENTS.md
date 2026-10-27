# Agent instructions — AdiSaan Imagination Observatory (hackathon project)

This repository is the open core of https://tbwim.adisaan.com, the AdiSaan
entry for the Meta Global AI Developer Hackathon (26 October – 4 November
2026). It is public under AGPL-3.0: never commit secrets, real answers, or
anything from the private AIOHM platform.

## Where it runs
- Production: 169.58.39.185 (vps.adisaan.com, SSH `adisaan-vps`), path
  `/var/www/tbwim.adisaan.com`, a git checkout of this repo on `main`. nginx vhost
  `observatory.conf`, database `observatory` on host MariaDB, queue worker
  `observatory-queue`, scheduler cron for `www-data`.
- The brand site adisaan.com and the admin at admin.adisaan.com are the AIOHM
  platform, a different application on the same server. This app does not
  render adisaan.com.

## Deploy
- Push to `main`, then on the server: `sudo bash /var/www/tbwim.adisaan.com/ops/deploy.sh`
  (fetch, reset to origin/main, composer install, migrate, caches, queue
  restart, local check). Pass a ref to deploy something else.
- Fresh host: `bash ops/provision-vps.sh <ssh host> tbwim.adisaan.com`.
- `.env`, `storage/` and `vendor/` are outside git. `META_API_KEY` and
  `META_MONTHLY_LIMIT_USD` live only in the server `.env`.
- A deploy is an approval boundary: do it when asked, report what the script
  printed, and verify `/api/agent/answer` from the edge afterwards.

## Working rules
- Run `php artisan test` before committing; keep the suite green.
- Keep the Meta spending ledger honest: every call reserves its worst case
  first. Never raise the monthly cap in code or config without the user's word.
- Human answers are released only by a person (`observatory:approve`); never
  automate that. Agent answers are reviewed by the model.
- The original text of an answer is never edited.
- Keep this repo the observatory alone. Multi-tenancy, admin panel, workflows,
  connectors, social publishing and brand memory stay in the private platform.
- Write in sentence case, plain language, no hype; the README sets the tone.
