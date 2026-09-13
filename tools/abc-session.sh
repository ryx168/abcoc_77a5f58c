#!/bin/bash
# Run the XOOPS shop behind an SSH reverse tunnel until it goes idle.
#
# Cloudflare Tunnel was replaced here after it proved unreliable at the data-plane
# level from this repo's GH Actions runners (control connection registered
# "healthy" while actual requests silently hung, on and off, for hours - see git
# history). This SSH reverse tunnel targets our own VPS (64.188.31.56, already
# proven reliable serving live mail) instead of a third-party tunnel network:
# `ssh -R` binds a port on the VPS's loopback that only its own nginx can reach,
# nginx proxies the public hostname to that port. The tunnel key is restricted
# (no shell, no forward-outbound, permitlisten pinned to that one port) so a
# leaked GH secret can only ever re-establish this exact reverse listener.
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

echo "TEMP DIAG: checking for php-fpm availability"
which php-fpm php-fpm5.6 php-fpm56 2>&1 || true
ls -la /usr/sbin/php-fpm* /usr/bin/php-fpm* 2>&1 || true
php -v

php -d error_reporting="E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_STRICT" \
    -d display_errors=0 -S 127.0.0.1:8080 router.php >/tmp/php.log 2>&1 &
sleep 3
echo "php -S started; local probe:"
curl -s -o /dev/null -w "  / -> %{http_code}\n" "http://127.0.0.1:8080/" || true

SSH_KEY_FILE="/tmp/abc_ssh_tunnel_key"
TUNNEL_PID=""
start_tunnel() {
  ssh -N -o StrictHostKeyChecking=no -o BatchMode=yes -o ServerAliveInterval=15 \
      -o ServerAliveCountMax=3 -o ExitOnForwardFailure=yes -o ConnectTimeout=10 \
      -i "$SSH_KEY_FILE" \
      -R "127.0.0.1:${SSH_TUNNEL_REMOTE_PORT}:127.0.0.1:8080" \
      "${SSH_TUNNEL_USER}@${SSH_TUNNEL_HOST}" >>/tmp/sshtun.log 2>&1 &
  TUNNEL_PID=$!
}

if [ -n "${SSH_TUNNEL_KEY:-}" ]; then
  ( umask 077; printf '%s\n' "$SSH_TUNNEL_KEY" > "$SSH_KEY_FILE" )
  start_tunnel
  echo "ssh reverse tunnel launched (pid $TUNNEL_PID); verifying end-to-end through the public hostname:"
  connected=0
  for i in $(seq 1 12); do
    sleep 5
    if ! kill -0 "$TUNNEL_PID" 2>/dev/null; then
      echo "  [${i}] ssh tunnel process died - last log lines:"; tail -20 /tmp/sshtun.log; break
    fi
    code=$(curl -s -o /dev/null -m 6 -w "%{http_code}" "https://${EDIT_HOST}/__diag" 2>/dev/null || echo "FAIL")
    if [ "$code" = "200" ]; then
      connected=1; echo "  [${i}] connected (public probe -> 200)"; break
    fi
    echo "  [${i}] not yet confirmed (public probe -> $code)"
  done
  if [ "$connected" = "1" ]; then
    echo "ssh tunnel connected; live at https://${EDIT_HOST}/"
    echo "self-test: 3 more round trips out through the VPS and back:"
    for i in 1 2 3; do
      curl -s -o /dev/null -m 15 -w "  [self-test $i] https://${EDIT_HOST}/__diag -> %{http_code} (%{time_total}s)\n" "https://${EDIT_HOST}/__diag" || echo "  [self-test $i] curl failed (exit $?)"
      sleep 3
    done
  else
    echo "WARNING: ssh tunnel did not confirm end-to-end connectivity within 60s - dumping log:"
    cat /tmp/sshtun.log 2>/dev/null
  fi
else
  echo "no SSH_TUNNEL_KEY - local only"
fi

IDLE_MIN="${IDLE_MINUTES:-10}"
HOLD_MIN="${CHECKOUT_HOLD_MINUTES:-40}"
idle_limit=$(( IDLE_MIN * 60 ))
hold_limit=$(( HOLD_MIN * 60 ))

activity_count() { grep -E "GET|POST" /tmp/php.log 2>/dev/null | grep -vc "__diag\|__phplog"; }
checkout_seen()  { grep -qE "checkout\.php|feedback\.php|response\.php" /tmp/php.log 2>/dev/null; }

last_count=$(activity_count); last_active=$(date +%s)
hold_until=0
last_persist=$(date +%s)
PERSIST_EVERY=180   # seconds - a crash never loses more than ~3 minutes of orders
last_diag=0
DIAG_EVERY=30   # seconds - dual local-vs-tunnel probe, to tell a hung php -S apart from a tunnel data-plane issue
echo "watching for idle (${IDLE_MIN} min normal, extends to a ${HOLD_MIN} min floor once checkout starts)"
MAX=$(( 340 * 60 )); start=$(date +%s)
while true; do
  sleep 15
  now=$(date +%s)
  if [ -n "${SSH_TUNNEL_KEY:-}" ] && ! kill -0 "$TUNNEL_PID" 2>/dev/null; then
    echo "  [$(date -u +%H:%M:%S)] ssh tunnel died - reconnecting"
    start_tunnel
  fi
  if [ $(( now - last_diag )) -ge $DIAG_EVERY ]; then
    lc=$(curl -s -o /dev/null -m 5 -w "%{http_code}" "http://127.0.0.1:8080/__diag" 2>/dev/null || echo "FAIL")
    tc=$(curl -s -o /dev/null -m 8 -w "%{http_code}" "https://${EDIT_HOST}/__diag" 2>/dev/null || echo "FAIL")
    echo "  [diag $(date -u +%H:%M:%S)] local(127.0.0.1:8080)=$lc  tunnel(${EDIT_HOST})=$tc"
    last_diag=$now
  fi
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
