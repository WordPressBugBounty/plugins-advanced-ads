# Conditional post-checkout license notice — design

**Date:** 2026-06-28  
**Status:** Implemented  
**Branch:** `improvement/6322-buy-redirect-checkout`  
**Plan:** [../plans/2026-06-28-conditional-post-checkout-notice.md](../plans/2026-06-28-conditional-post-checkout-notice.md)  
**Scope:** Post-checkout success banner copy and URL handling in the client plugin (`advanced-ads`). No shop checkout changes.

## Goal

After purchase, upgrade, or renewal, the green success banner on the Licenses screen must show **contextual title and subtitle** instead of always using the new-purchase copy.

Today `handlePostCheckoutNotice()` in `LicenseNotices.jsx` always shows:

-   **Title:** License purchased successfully
-   **Body:** Your license is ready. Use 'Download and activate' to set it up on this site.

This spec defines conditional copy based on checkout intent and on-site license setup state.

## Non-negotiable: copy only — no functionality changes

**This is a text-only change.** Implementation must not alter how the license system works.

| Unchanged                   | Details                                                                                 |
| --------------------------- | --------------------------------------------------------------------------------------- |
| **When the notice appears** | Still only when `?purchase_id=` is present and `isLoading === false`                    |
| **Dedup**                   | Same `shownPurchaseIds` Set keyed by `purchase_id`                                      |
| **Token exchange**          | No changes to `useLicenseData`, `exchangeTokenAndSave`, or shop activate/save           |
| **License data**            | No changes to REST, PHP, persistence, addon maps, or install/activate flows             |
| **Notice UX**               | Same banner component, green variant, `icon: 'loading'`, dismiss, no auto-dismiss timer |
| **Other notices**           | Expiry, shop errors, Download/activate success/error — untouched                        |
| **Side effects**            | No new API calls, activations, installs, or store writes from notice logic              |

Allowed changes:

-   **Strings** shown in the post-checkout success title and body.
-   **Read-only** use of existing URL params and store data to pick those strings.
-   **URL cleanup** — extend the existing `replaceLicenseUrl` omit list to remove `checkout_intent` and `license_id` after the notice is shown (same moment `purchase_id` / `token` are already cleared; cosmetic only).

New helpers in `utils.js` are **pure functions** for copy selection only — they must not mutate licenses, options, or plugin state.

## Constraints

-   **Client-only** — read `checkout_intent` and `license_id` from the return URL; compute copy in JS after token exchange and license load. No new REST fields or shop PHP changes (shop already appends query args on redirect).
-   **License-level setup state (option A)** — for All Access, “ready” means the **site slot** is on the entitled license row, not that every included add-on is installed.
-   **No visual changes** — same green banner, `icon: 'loading'`, dismiss behavior as today.
-   **Backward compatible** — missing `checkout_intent` defaults to `buy` (purchase title + existing download subtitle rules).
-   **i18n** — all strings via `@wordpress/i18n` `__()` in the client plugin; update `languages/advanced-ads.pot` after implementation.

## Out of scope

-   Activation-button success/error notices (`DownloadActivateButton.jsx`).
-   Expiry and shop query error banners.
-   Changing when the notice appears (still `?purchase_id=` + `!isLoading`, deduped by `purchase_id`).
-   Auto-dismiss on post-checkout notice (unchanged — no timer today).
-   Any license, activation, upgrade-migration, or checkout behavior beyond choosing banner text.

---

## Inputs

### URL query parameters (shop → plugin redirect)

Already set by `advanced-ads-licenses` checkout redirect (`includes/checkout/class-checkout.php`):

| Param             | Values                        | Purpose                                    |
| ----------------- | ----------------------------- | ------------------------------------------ |
| `purchase_id`     | EDD payment ID                | Triggers notice; dedup key                 |
| `token`           | Exchange token                | Consumed by `useLicenseData` before notice |
| `checkout_intent` | `buy` \| `upgrade` \| `renew` | Title selection                            |
| `license_id`      | EDD SL license post ID        | Prefer this row for setup-state evaluation |

Also strip `checkout_intent` and `license_id` from the URL when clearing `purchase_id` and `token`.

### Store data (after exchange)

Available in `useLicenseNoticesSync` via `STORE_NAME`:

-   `licenses` — rich license rows
-   `appliedAddonKeyMap` — addon id → license key
-   `addonInstallStates` — per-addon `{ installed, active }` from REST
-   `currentHostname` — from `advancedAds.endpoints.siteUrl` (same as license screen)

Notice must **not** render until `isLoading === false` so exchange and `addonInstallStates` are current.

---

## Copy specification

### Title (from `checkout_intent`)

| Intent            | Title                          |
| ----------------- | ------------------------------ |
| `buy`             | License purchased successfully |
| `upgrade`         | License upgraded successfully  |
| `renew`           | License renewed successfully   |
| missing / invalid | License purchased successfully |

Normalize with `sanitize_key` equivalent: only `buy`, `upgrade`, `renew` accepted; anything else → `buy`.

### Subtitle (from setup state)

Use straight single quotes around button labels in translatable strings.

| State      | Condition                                                                                              | Body                                                                          |
| ---------- | ------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------- |
| `ready`    | Site is on the post-checkout license row (see below)                                                   | Your license is ready.                                                        |
| `activate` | Site **not** on license; license is **single-product**; its add-on is **installed** and **not active** | Your license is ready. Use 'activate' to set it up on this site.              |
| `download` | All other cases                                                                                        | Your license is ready. Use 'Download and activate' to set it up on this site. |

**All Access:** evaluate `ready` with `isAllAccessActiveOnThisSite(licenses, hostname)` on the post-checkout row when that row is an All Access bundle. If not ready, always use `download` (per-add-on actions remain in the card; no `activate`-only subtitle for AA).

**Upgrade / renew:** when site slot was migrated to the new key (see license upgrade work), `ready` is expected — subtitle is “Your license is ready.” without asking to activate again.

---

## Setup state logic

### 1. Resolve post-checkout license row

`resolvePostCheckoutLicenseRow(licenses, licenseIdFromUrl)`:

1. If `license_id` > 0, return the row whose `licenseId` matches.
2. Else return `findEntitledAllAccessLicense(licenses)` when present.
3. Else return the first entitled single-product row with the newest `licenseId` (highest numeric id wins).

If no row matches, fall back to title/subtitle for `buy` + `download` (safe default).

### 2. Classify setup state

`getPostCheckoutSetupState(row, licenses, hostname, appliedAddonKeyMap, addonInstallStates)`:

```
if row is All Access bundle:
  if isAllAccessActiveOnThisSite(licenses, hostname):
    return 'ready'
  return 'download'

if isCurrentSiteActivatedOnLicense(row, hostname):
  return 'ready'

addonId = resolveAddonIdForLicense(row, appliedAddonKeyMap)
if addonId:
  { installed, active } = getAddonInstallState(addonId, addonInstallStates)
  if installed && !active:
    return 'activate'

return 'download'
```

Reuse existing helpers from `src/admin/screen-licenses/utils.js` (`isAllAccessBundleName`, `isCurrentSiteActivatedOnLicense`, `resolveAddonIdForLicense`, `getAddonInstallState`, `findEntitledAllAccessLicense`).

### 3. Build notice copy

`buildPostCheckoutNoticeCopy({ checkoutIntent, licenses, licenseId, hostname, appliedAddonKeyMap, addonInstallStates })` → `{ title, message }`.

Pure function; fully unit-testable.

---

## Component changes

### `LicenseNotices.jsx`

-   `useLicenseNoticesSync`: pass `checkout_intent`, `license_id`, `licenses`, `appliedAddonKeyMap`, `addonInstallStates`, and hostname into `handlePostCheckoutNotice`.
-   `handlePostCheckoutNotice`: replace hard-coded strings with `buildPostCheckoutNoticeCopy(...)`.
-   `replaceLicenseUrl`: omit `checkout_intent` and `license_id` along with `purchase_id` and `token`.

### `utils.js`

Add exports:

-   `normalizeCheckoutIntent(queryIntent)`
-   `resolvePostCheckoutLicenseRow(licenses, licenseId)`
-   `getPostCheckoutSetupState(...)`
-   `buildPostCheckoutNoticeCopy(...)`

No changes to `DownloadActivateButton.jsx` unless duplicate “License purchased successfully” there is noticed during QA (out of scope unless requested).

---

## Data flow

```
Shop redirect
  → /license?token&purchase_id&checkout_intent&license_id
useLicenseData: exchange token → save licenses → isLoading false
useLicenseNoticesSync: purchase_id set
  → buildPostCheckoutNoticeCopy()
  → publishLicenseSuccessNotice (POST_CHECKOUT_NOTICE_ID)
  → replaceLicenseUrl (strip checkout params)
```

Dedup: existing `shownPurchaseIds` Set unchanged.

---

## Testing

### Unit tests (`utils.js`)

| Case                              | Intent  | Setup    | Expected title | Expected body contains |
| --------------------------------- | ------- | -------- | -------------- | ---------------------- |
| Fresh buy, nothing installed      | buy     | download | purchased      | Download and activate  |
| Fresh buy, installed inactive Pro | buy     | activate | purchased      | 'activate'             |
| Upgrade, site on new AA           | upgrade | ready    | upgraded       | Your license is ready. |
| Renew, site on license            | renew   | ready    | renewed        | Your license is ready. |
| Missing intent                    | —       | download | purchased      | Download and activate  |
| Invalid intent `foo`              | foo     | download | purchased      | Download and activate  |
| AA, site on license               | upgrade | ready    | upgraded       | Your license is ready. |
| AA, no site slot                  | buy     | download | purchased      | Download and activate  |

### Playwright

-   `license-purchase-flow.spec.ts` — `checkout_intent=upgrade` and `renew`; params stripped.
-   `license-expiry-notices.spec.ts` — `purchase_id` only → purchase title (backward compat).

---

## Acceptance criteria

-   [x] **No functionality regression** — token exchange, license save, activation, and notice trigger/dedup/dismiss behave exactly as before.
-   [x] Title reflects `buy` / `upgrade` / `renew` from URL; defaults to purchase when missing.
-   [x] Subtitle reflects `ready` / `activate` / `download` per license-level rules; AA uses `ready` or `download` only.
-   [x] Notice still waits for license load; same dedup and dismiss behavior.
-   [x] `checkout_intent` and `license_id` removed from URL after notice (extends existing strip only).
-   [x] Unit tests cover builder and setup-state helpers (copy selection only).
-   [x] `.pot` updated for new strings.

---

## Related docs

-   `docs/superpowers/specs/2026-06-18-license-post-checkout-notice-design.md` — banner UI (superseded for **copy** of case 1 only).
-   Shop redirect: `advanced-ads-licenses/includes/checkout/class-checkout.php` (`checkout_intent`, `license_id` query args).

---

## Implementation note

Implemented per [../plans/2026-06-28-conditional-post-checkout-notice.md](../plans/2026-06-28-conditional-post-checkout-notice.md). Key files: `utils.js` (copy helpers), `LicenseNotices.jsx` (wiring), `postCheckoutNotice.test.js`, `license-purchase-flow.spec.ts`.
