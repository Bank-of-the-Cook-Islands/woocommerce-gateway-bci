"""Drives one guest checkout on the sandbox store through BPC's hosted payment page.

Runs inside the Playwright container that tests/sandbox/sandbox.sh builds. Prints a
JSON summary on stdout and writes screenshots to <workdir>/shots.
"""

import argparse
import json
import os
import re
import sys
import time
from urllib.parse import urlparse

from playwright.sync_api import sync_playwright

# BPC's hosted page answers a HeadlessChrome user agent with HTTP 412 and a
# blank page, so the browser presents itself as ordinary desktop Chrome.
USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"
)

parser = argparse.ArgumentParser()
parser.add_argument("--site", required=True, help="store base URL")
parser.add_argument("--tag", required=True, help="names the customer, email and screenshots")
parser.add_argument("--product", type=int, required=True)
parser.add_argument(
    "--mode",
    default="pay",
    choices=["methods", "stop", "pay", "abandon"],
    help="methods: list checkout payment methods; stop: stop on the BPC page; "
    "pay: pay and return; abandon: pay but never return to the store",
)
parser.add_argument("--card", default="4111111111111111")
parser.add_argument("--expiry", default="12/26")
parser.add_argument("--cvc", default="123")
parser.add_argument("--acs", default="Success", choices=["Success", "Fail"], help="3-D Secure challenge button")
parser.add_argument("--fill-address", action="store_true", help="also fill State and Postcode")
parser.add_argument("--pause", action="store_true", help="wait on the BPC page until <workdir>/go exists")
parser.add_argument("--workdir", default="/work")
args = parser.parse_args()

store_host = re.escape(urlparse(args.site).netloc)
shots = os.path.join(args.workdir, "shots")
os.makedirs(shots, exist_ok=True)
out = {"tag": args.tag}


def shot(page, name):
    page.screenshot(path=os.path.join(shots, f"{args.tag}-{name}.png"), full_page=True)


def finish(code=0):
    print(json.dumps(out, indent=1))
    sys.exit(code)


with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_page(viewport={"width": 1280, "height": 1000}, user_agent=USER_AGENT, locale="en-NZ")

    page.goto(f"{args.site}/?add-to-cart={args.product}")
    page.goto(f"{args.site}/checkout/")
    page.wait_for_selector("#email", timeout=30000)
    page.fill("#email", f"{args.tag}@example.com")

    fields = [
        ("#billing-first_name", "Test"),
        ("#billing-last_name", args.tag),
        ("#billing-address_1", "Main Road"),
        ("#billing-city", "Avarua"),
        ("#billing-phone", "29000"),
    ]
    if args.fill_address:
        fields += [("#billing-state", "Rarotonga"), ("#billing-postcode", "0000")]
    for selector, value in fields:
        if page.locator(selector).count():
            page.fill(selector, value)

    methods = page.locator("input[name='radio-control-wc-payment-method-options']")
    out["checkout_methods"] = [methods.nth(i).get_attribute("value") for i in range(methods.count())]
    shot(page, "1-checkout")
    if args.mode == "methods":
        finish()

    # The Blocks checkout re-validates the cart after every field change, and a
    # click made while that is in flight is silently dropped.
    page.check("#radio-control-wc-payment-method-options-bci_takuecom", force=True)
    page.locator("#billing-city").press("Tab")
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(2000)
    button = page.locator(".wc-block-components-checkout-place-order-button")
    button.click()
    page.wait_for_timeout(3000)
    if "bpcbt" not in page.url:
        button.click()

    try:
        page.wait_for_url(re.compile(r"bpcbt\.com"), timeout=45000)
    except Exception:
        shot(page, "2-stuck")
        out["notices"] = page.eval_on_selector_all(
            ".wc-block-components-notice-banner, .wc-block-components-validation-error",
            "els => els.map(e => e.innerText)",
        )
        finish(1)

    page.wait_for_load_state("networkidle")
    page.wait_for_selector("input[name=pan]", timeout=30000)
    out["payment_page"] = page.url.split("?")[0]
    shot(page, "2-bpc")

    if args.pause:
        open(os.path.join(args.workdir, "at-bpc"), "w").write(page.url)
        for _ in range(300):
            if os.path.exists(os.path.join(args.workdir, "go")):
                break
            time.sleep(1)

    if args.mode == "stop":
        finish()

    page.fill("input[name=pan]", args.card)
    page.fill("input[name=expiry]", args.expiry)
    page.fill("input[name=newCardCvc]", args.cvc)
    page.fill("input[name=cardholderName]", "TEST CARD")
    shot(page, "3-filled")

    if args.mode == "abandon":
        # The customer closes the tab after paying: the browser never reaches the store again.
        page.route(re.compile(store_host), lambda route: route.abort())

    page.click("button[type=submit]")

    try:
        page.wait_for_url(re.compile(r"acs|" + store_host), timeout=45000)
    except Exception:
        pass
    if "acs" in page.url:
        page.wait_for_load_state("networkidle")
        shot(page, "3b-acs")
        out["acs"] = args.acs
        page.get_by_role("button", name=args.acs, exact=True).click()

    if args.mode == "pay":
        try:
            page.wait_for_url(re.compile(store_host + r"/.*(order-received|checkout)"), timeout=90000)
        except Exception:
            pass
    page.wait_for_timeout(4000)
    out["final_url"] = page.url.split("?")[0]
    shot(page, "4-after")
    browser.close()
    finish()
