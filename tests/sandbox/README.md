# BPC sandbox harness

A WordPress + WooCommerce store running this checkout, plus a headless browser that pays through BPC's real sandbox (`dev.bpcbt.com`). Run it before a release: the unit suite and `tests/wordpress/run.sh` stub or skip the gateway, and this does not.

It is manual rather than part of CI, because it needs sandbox credentials, reaches the internet, and with `--tunnel` exposes a store publicly.

## Requirements

- Docker. The first run pulls `mcr.microsoft.com/playwright/python` (about 3.4 GB).
- BPC sandbox API credentials. On localdev they come from Bitwarden through `wh-secret`.
- For callbacks: a sandbox callback token from the BPC sandbox merchant portal, and `--tunnel`.

## Usage

```bash
tests/sandbox/sandbox.sh up                 # local only, http://localhost:8089
BCI_SANDBOX_API_LOGIN="$(wh-secret bpc_sandbox_api_login)" \
BCI_SANDBOX_API_PASSWORD="$(wh-secret bpc_sandbox_api_password)" \
  tests/sandbox/sandbox.sh configure        # saves credentials, runs the connection test
tests/sandbox/sandbox.sh scenarios          # runs the checkouts, prints each order's notes
tests/sandbox/sandbox.sh down               # removes the store and its data
```

The plugin is mounted from the working tree, so check out a branch and rerun `scenarios` to test it. Screenshots and each checkout's JSON summary land in `~/.local/state/wc-bci-sandbox/run/`.

### Testing callbacks

`up --tunnel` publishes the store through a Cloudflare quick tunnel instead. The URL is random and changes on every `up`.

1. Run `up --tunnel` and note the callback URL it prints.
2. In the BPC sandbox merchant portal, under Settings > Callback notifications, point the callback at that URL. Use Static, POST, Symmetric signing, and the Deposited, Approved, Reversed, Refunded, and Declined by timeout events. Generate a token.
3. Pass the token to `configure` as `BCI_SANDBOX_CALLBACK_TOKEN`.
4. Run `scenarios`. It adds the two cases where the customer never returns to the store, which only a callback can settle.
5. Run `down` as soon as you have finished, then rotate the token in the portal.

## Scenarios

| Scenario | Card | Expected order |
| --- | --- | --- |
| `paid` | 4111 1111 1111 1111 | Processing |
| `declined-cvc` | 4111…, CVC 999 | Failed, action code 71015 |
| `declined-3ds` | 5168 4948 9505 5780, challenge Fail | Failed, action code -2025 |
| `paid-3ds-challenge` | 5555 5555 5555 5599, challenge Success | Processing |
| `cancelled-then-paid` | 4111…, order cancelled while on BPC's page | Cancelled, then Processing |
| `paid-never-returned` (tunnel) | 4111…, browser never returns | Processing, from the callback |
| `cancelled-then-paid-never-returned` (tunnel) | as above, after cancellation | Cancelled, then Processing, from the callback |

Every order should end with exactly one outcome note and `duplicated_meta=none`. Checkouts use a Cook Islands address with no state or postcode. Test cards are listed at https://dev.bpcbt.com/en/integration/structure/test-cards.html.

For a single checkout, `sandbox.sh checkout --tag NAME [--mode methods|stop|pay|abandon] [--card N --expiry MM/YY --cvc N --acs Success|Fail] [--fill-address]`.

## Gotchas

- BPC's hosted page returns HTTP 412 and a blank page to a `HeadlessChrome` user agent, so `checkout.py` presents as desktop Chrome.
- The Blocks checkout drops a Place Order click made while it is still re-validating the cart, so the script waits and clicks again.
- The BPC sandbox charges in EUR by default. `configure` keeps the plugin's sandbox currency on EUR.
