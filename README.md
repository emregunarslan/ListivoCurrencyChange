# Listivo Currency Converter

A standalone WordPress plugin for Listivo ad forms. It keeps a **CAD** price box and a **USD** price box in sync while the seller types:

- Type in CAD → the USD box fills in automatically.
- Type in USD → the CAD box fills in automatically.
- The auto-filled box becomes read-only until the source box is cleared, so both prices cannot drift apart.
- The converted value is written into the real Listivo field and saved with the ad.

No theme files are edited. Tested against Listivo / listivo-core **2.3.93** on [racecarandgear.com](https://racecarandgear.com/).

## Requirements

- WordPress 6.0 or newer, PHP 7.4 or newer.
- The Listivo theme with two price boxes on the ad submission form.
- PHP's SimpleXML extension (enabled on virtually all hosts) for reading the rate feed.

## Installation

1. Zip this folder (the folder must contain `listivo-currency-converter.php`).
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the zip and activate it.
3. Go to **Settings → Listivo Currency**.

## Default configuration

The defaults match the current field IDs on racecarandgear.com:

| Setting | Default |
| --- | --- |
| CAD field | `#listivo_130_listivo_13` — the required **Price (CAD)** field |
| USD field | `#listivo_9145` — the **Price in Other Currencies** text field |
| Fallback rate | `1.4488` CAD per USD |
| CAD rounding | nearest 5 |
| USD rounding | nearest 1 |
| Decimals | 0 |

If the IDs change (for example, after recreating a custom field), update the two selectors. If a selector is empty or no longer matches, the plugin falls back to looking for `CAD` / `USD` in the field labels.

If the main Listivo **Price** field is actually USD, swap the two selectors: the rate is always quoted as **1 USD = X CAD**.

## How the rate works

- Rates are read from the **European Central Bank daily reference feed** (no API key or account).
- USD/CAD is derived from the ECB's EUR-based quotes.
- The rate is cached in the `lcc_rate_data` option for 12 hours and refreshed twice daily by WP-Cron.
- The ad form only reads the cached rate, so it never waits on the feed; the settings screen and WP-Cron refresh it.
- If the feed is unreachable, the last successful rate is kept; on a fresh install the configured fallback rate is used and the hint says "fallback rate".
- **Settings → Listivo Currency → Refresh rate now** forces an update.
- Developers can filter the rate with `lcc_usd_to_cad_rate` and the enqueue condition with `lcc_should_enqueue`.

## What gets written

- A Listivo **price** field receives a plain number, e.g. `2761`.
- A Listivo **text** field receives an amount and code, e.g. `2,761 USD`, matching the format already used by existing ads.
- If you later change the second field to a Listivo Price field, the plugin automatically writes a plain number instead.

## Notes

- The script only loads on `/panel/` URLs (the create/edit listing screens). Use the `lcc_should_enqueue` filter if the form is on another URL.
- The existing Baxtersweb display-currency plugin is left untouched; this plugin only changes what is entered in the ad form.
- The hint under the converted box shows the rate used and whether it is a fallback.
