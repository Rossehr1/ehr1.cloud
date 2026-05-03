#!/bin/bash
# Probe Hostinger developers API with ~/.api_token (optional).
set -euo pipefail
TOKEN=$(tr -d '\n\r' < ~/.api_token)
URL="https://developers.hostinger.com/api/domains/v1/portfolio"
code=$(curl -sS -o /tmp/hi_dom.json -w "%{http_code}" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" \
  "$URL")
echo "GET ${URL} -> ${code}"
head -c 2000 /tmp/hi_dom.json 2>/dev/null; echo
