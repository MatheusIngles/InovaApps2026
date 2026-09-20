#!/bin/sh
# Instala os serviços do Seer no systemd. Rode com: sudo sh deploy/instalar-servicos.sh
set -e
cd "$(dirname "$0")"

# para os processos soltos (nohup) que estejam usando a porta 8000
pkill -f "artisan serve" || true
pkill -f "queue:work" || true
pkill -f "php -S" || true

cp seer-web.service seer-queue.service seer-saude.service seer-saude.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now seer-web seer-queue seer-saude.timer

sleep 4
systemctl is-active seer-web seer-queue seer-saude.timer
curl -s -o /dev/null -w "site local: HTTP %{http_code}\n" http://127.0.0.1:8000/login
