#!/bin/bash
# Run the XOOPS shop behind the Cloudflare tunnel until it goes idle.
#
# Idle is measured by requests to checkout-relevant routes (cart/checkout/goods/
# the NewebPay callback endpoints) - not admin/browsing noise, since this repo
# only ever serves customers who were routed here by /__checkout-start.
#
# Payment safety: NewebPay redirects the customer OFF our server to pay, then
# calls back asynchronously (feedback.php/response.php). No requests hit us
# during that gap, so a pure idle-timer could kill the session while a customer
# is off paying. Once a checkout.php request is seen, the idle deadline is
# pushed out to a fixed minimum floor (CHECKOUT_HOLD_MINUTES) from that moment,
# not just extended by the normal idle window - covering the external round trip
# even if nothing else touches us until the callback arrives.
set -uo pipefail
TOOLS_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "${GITHUB_WORKSPACE:-$PWD}/webroot"

php -d error_reporting="E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_STRICT" \
    -d display_errors=0 -S 127.0.0.1:8080 router.php >/tmp/php.log 2>&1 &
sleep 3
echo "php -S started; local probe:"
curl -s -o /dev/null -w "  / -> %{http_code}\n" "http://127.0.0.1:8080/" || true

if [ -n "${TUNNEL_TOKEN:-}" ]; then
  curl -fsSL -o /tmp/cloudflared "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64"
  chmod +x /tmp/cloudflared
  # --protocol http2: GH Actions runners' network handles the default QUIC/UDP
  # transport strangely - the tunnel registers as a healthy connection (control
  # plane over QUIC works) but request data never actually flows through it
  # (every proxied request hangs with zero bytes, even though the same origin
  # answers instantly on localhost and other tunnels on this same zone/account
  # respond normally). Forcing TCP-based HTTP/2 avoids the UDP data plane
  # entirely and is the standard fix for this exact "healthy but silent" symptom.
  /tmp/cloudflared tunnel --no-autoupdate --protocol http2 --loglevel info run --token "$TUNNEL_TOKEN" >/tmp/cfd.log 2>&1 &
  echo "cloudflared launched (pid $!); verifying it actually registers a connection:"
  registered=0
  for i in $(seq 1 12); do
    sleep 5
    if grep -qi "Registered tunnel connection\|Connection .*registered" /tmp/cfd.log 2>/dev/null; then
      registered=1; echo "  [${i}] connected"; break
    fi
    if ! kill -0 $! 2>/dev/null; then
      echo "  [${i}] cloudflared process died - last log lines:"; tail -20 /tmp/cfd.log; break
    fi
    echo "  [${i}] not yet connected"
    tail -3 /tmp/cfd.log 2>/dev/null | sed 's/^/    /'
  done
  if [ "$registered" = "1" ]; then
    echo "cloudflared connected; live at https://${EDIT_HOST}/"
  else
    echo "WARNING: cloudflared did not confirm a registered connection within 60s - dumping full log:"
    cat /tmp/cfd.log 2>/dev/null
  fi
else
  echo "no TUNNEL_TOKEN - local only"
fi

IDLE_MIN="${IDLE_MINUTES:-10}"
HOLD_MIN="${CHECKOUT_HOLD_MINUTES:-40}"
idle_limit=$(( IDLE_MIN * 60 ))
hold_limit=$(( HOLD_MIN * 60 ))

activity_count() { grep -cE "GET|POST" /tmp/php.log 2>/dev/null || echo 0; }
checkout_seen()  { grep -qE "checkout\.php|feedback\.php|response\.php" /tmp/php.log 2>/dev/null; }

last_count=$(activity_count); last_active=$(date +%s)
hold_until=0
last_persist=$(date +%s)
PERSIST_EVERY=180   # seconds - a crash never loses more than ~3 minutes of orders
echo "watching for idle (${IDLE_MIN} min normal, extends to a ${HOLD_MIN} min floor once checkout starts)"
MAX=$(( 340 * 60 )); start=$(date +%s)
while true; do
  sleep 15
  now=$(date +%s)
  c=$(activity_count)
  if [ "$c" != "$last_count" ]; then
    last_count=$c; last_active=$now
    if checkout_seen; then
      candidate=$(( now + hold_limit ))
      [ $candidate -gt $hold_until ] && hold_until=$candidate
    fi
  fi
  if [ $(( now - last_persist )) -ge $PERSIST_EVERY ]; then
    bash "$TOOLS_DIR/abc-persist.sh" || echo "  periodic persist failed (will retry next cycle)"
    last_persist=$now
  fi
  idle=$(( now - last_active ))
  if [ $now -lt $hold_until ]; then
    : # inside the payment-safety floor - never idle out yet, regardless of the plain timer
  elif [ $idle -ge $idle_limit ]; then
    echo "idle ${idle}s >= ${idle_limit}s - stopping"; break
  fi
  [ $(( now - start )) -ge $MAX ] && { echo "max session time - stopping"; break; }
done
echo "session ended (requests logged: $(activity_count))"
