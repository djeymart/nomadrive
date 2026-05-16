#!/usr/bin/env python3
"""
NOMADRIVE — Scraper GYG local (Mac)
Scrape les avis GYG via Playwright et les pousse vers le serveur.

Usage:
    python3 scrape_gyg.py

Prérequis:
    pip3 install playwright
    python3 -m playwright install chromium
"""

import asyncio
import json
import re
import sys
import urllib.request
import urllib.error
from datetime import date
from playwright.async_api import async_playwright

# ── Configuration ──────────────────────────────────────────────────────────────
GYG_URL      = "https://www.getyourguide.com/nice-l314/discover-the-riviera-and-nice-by-electric-vehicle-t1285889/"
SERVER_URL   = "https://nomadrive.fr/push_reviews.php"
PUSH_TOKEN   = "ndpush_k9x2m7q4r8w1z3p5"
# ──────────────────────────────────────────────────────────────────────────────


def relative_date(iso_date: str) -> str:
    try:
        delta = (date.today() - date.fromisoformat(iso_date[:10])).days
    except ValueError:
        return ""
    if delta == 0:   return "aujourd'hui"
    if delta == 1:   return "il y a 1 jour"
    if delta < 7:    return f"il y a {delta} jours"
    if delta < 14:   return "il y a une semaine"
    if delta < 30:   return f"il y a {delta // 7} semaines"
    if delta < 60:   return "il y a un mois"
    return f"il y a {delta // 30} mois"


async def scrape() -> dict:
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent=(
                "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
                "AppleWebKit/537.36 (KHTML, like Gecko) "
                "Chrome/124.0.0.0 Safari/537.36"
            ),
            locale="fr-FR",
        )
        page = await context.new_page()
        await page.goto(GYG_URL, wait_until="networkidle", timeout=30000)
        await page.wait_for_timeout(3000)

        jsonld_blocks = await page.evaluate("""
            () => Array.from(document.querySelectorAll('script[type="application/ld+json"]'))
                       .map(s => s.textContent)
        """)
        await browser.close()

    jsonld = None
    for block in jsonld_blocks:
        try:
            decoded = json.loads(block)
            if "review" in decoded:
                jsonld = decoded
                break
        except json.JSONDecodeError:
            pass

    if not jsonld:
        print("ERREUR : aucun bloc JSON-LD avec reviews trouvé.")
        sys.exit(1)

    overall_rating = jsonld.get("aggregateRating", {}).get("ratingValue", 0)
    total_count    = jsonld.get("aggregateRating", {}).get("reviewCount", 0)

    reviews = []
    for r in jsonld.get("review", []):
        rating = int(r.get("reviewRating", {}).get("ratingValue", 0))
        text   = (r.get("reviewBody") or "").strip()
        author = r.get("author", {}).get("name", "Anonyme")
        iso    = (r.get("datePublished") or "")[:10]

        if rating < 5 or not text:
            continue

        slug = re.sub(r"[^a-z0-9]", "_", author.lower())
        reviews.append({
            "external_review_id": f"gyg_{slug}_{iso}",
            "author_name":        "Voyageur GYG" if author == "Voyageur·se GetYourGuide" else author,
            "author_photo_url":   None,
            "rating":             rating,
            "review_text":        text,
            "relative_date":      relative_date(iso),
        })

    return {
        "source":         "gyg",
        "overall_rating": round(float(overall_rating), 1),
        "total_count":    int(total_count),
        "reviews":        reviews,
    }


def push(payload: dict) -> None:
    import ssl, certifi
    data = json.dumps(payload).encode("utf-8")
    req  = urllib.request.Request(
        SERVER_URL,
        data=data,
        headers={
            "Content-Type":  "application/json",
            "X-Push-Token":  PUSH_TOKEN,
        },
        method="POST",
    )
    ctx = ssl.create_default_context(cafile=certifi.where())
    try:
        with urllib.request.urlopen(req, timeout=15, context=ctx) as resp:
            result = json.loads(resp.read())
            print(f"Serveur : {result}")
    except urllib.error.HTTPError as e:
        print(f"Erreur HTTP {e.code} : {e.read().decode()}")
    except Exception as e:
        print(f"Erreur : {e}")


async def main():
    print("Scraping GYG...")
    payload = await scrape()
    print(f"  {len(payload['reviews'])} avis 5★ trouvés (total GYG : {payload['total_count']}, note : {payload['overall_rating']})")
    for r in payload["reviews"]:
        print(f"  - {r['author_name']} : {r['review_text'][:60]}...")
    print("Envoi au serveur...")
    push(payload)


asyncio.run(main())
