#!/usr/bin/env bash
# Read-only look at a server before putting the observatory on it.
# Usage: bash ops/audit-vps.sh <ssh host alias or user@ip>
set -euo pipefail
HOST="${1:?usage: audit-vps.sh <ssh host>}"
ssh -o ConnectTimeout=12 "$HOST" 'bash -s' <<'REMOTE'
echo "== host"; hostname; grep -E "^(PRETTY_NAME|VERSION_ID)" /etc/os-release; uptime
echo "== resources"; nproc; free -h | sed -n 2p; df -h / | tail -1
echo "== running services (filtered)"
systemctl list-units --type=service --state=running --no-pager --no-legend | awk '{print $1}' \
  | grep -Ev 'systemd|dbus|getty|cron|ssh|rsyslog|polkit|networkd|resolved|udev|journald|logind|snapd|apparmor|unattended|qemu|multipath|ModemManager|accounts|irqbalance|lvm' || true
echo "== listening ports"; ss -tlnp 2>/dev/null | awk 'NR>1{print $4, $6}' | sort -u
echo "== php"; php -v 2>/dev/null | head -1 || echo "no php"; ls /etc/php 2>/dev/null || true
echo "== database"; mysql --version 2>/dev/null || mariadb --version 2>/dev/null || echo "no mysql client"
echo "== nginx sites"; ls /etc/nginx/sites-enabled 2>/dev/null || echo "no nginx"
echo "== /var/www"; ls /var/www 2>/dev/null || true
echo "== cron"; crontab -l 2>/dev/null | grep -v '^#' || true; ls /etc/cron.d 2>/dev/null || true
for u in $(cut -d: -f1 /etc/passwd); do c=$(crontab -l -u "$u" 2>/dev/null | grep -v '^#' || true); [ -n "$c" ] && { echo "[$u]"; echo "$c"; }; done
echo "== backup-looking directories"; du -sh /var/backups /root/backups /backup /backups /home/*/backups 2>/dev/null || true
echo "== docker"; docker ps --format '{{.Names}} {{.Image}}' 2>/dev/null || echo "no docker"
REMOTE
