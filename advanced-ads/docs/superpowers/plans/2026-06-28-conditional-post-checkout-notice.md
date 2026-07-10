# Conditional Post-Checkout Notice — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Status:** Implemented (branch `improvement/6322-buy-redirect-checkout`)

**Goal:** Show contextual post-checkout success banner title and subtitle from `checkout_intent` and on-site license setup state; strip `checkout_intent` and `license_id` from the URL after the notice.

**Architecture:** Pure copy-selection helpers in `utils.js`; `LicenseNotices.jsx` reads URL params and store data after `isLoading === false`. No REST, PHP, token exchange, or activation changes.

**Tech Stack:** React (`@wordpress/element`), `@wordpress/i18n`, Jest (jsdom), Playwright

**Spec:** [../specs/2026-06-28-conditional-post-checkout-notice-design.md](../specs/2026-06-28-conditional-post-checkout-notice-design.md)

**Repo:** `advanced-ads` (client only)

---

## File map

| File                                                             | Responsibility                                                                                                         |
| ---------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `src/admin/screen-licenses/utils.js`                             | `normalizeCheckoutIntent`, `resolvePostCheckoutLicenseRow`, `getPostCheckoutSetupState`, `buildPostCheckoutNoticeCopy` |
| `src/admin/screen-licenses/components/LicenseNotices.jsx`        | Wire builder into `handlePostCheckoutNotice`; extend `POST_CHECKOUT_URL_OMIT`                                          |
| `src/admin/screen-licenses/postCheckoutNotice.test.js`           | Unit tests for copy helpers                                                                                            |
| `tests/Acceptance/Admin/Licenses/license-purchase-flow.spec.ts`  | Playwright upgrade/renew notice + param strip                                                                          |
| `tests/Acceptance/Admin/Licenses/license-expiry-notices.spec.ts` | Playwright purchase-only backward compat                                                                               |
| `languages/advanced-ads.pot`                                     | New translatable strings                                                                                               |

---

## Task 1: Copy-selection helpers (`utils.js`)

**Files:**

-   Modify: `src/admin/screen-licenses/utils.js`

-   [x] **Step 1: Add `normalizeCheckoutIntent`**
-   [x] **Step 2: Add `resolvePostCheckoutLicenseRow`**
-   [x] **Step 3: Add `getPostCheckoutSetupState`**
-   [x] **Step 4: Add `buildPostCheckoutNoticeCopy`**

---

## Task 2: Wire notice component

**Files:**

-   Modify: `src/admin/screen-licenses/components/LicenseNotices.jsx`

-   [x] **Step 1: Extend `POST_CHECKOUT_URL_OMIT` with `checkout_intent` and `license_id`**
-   [x] **Step 2: Pass `licenses`, `appliedAddonKeyMap`, `addonInstallStates` into `handlePostCheckoutNotice`**
-   [x] **Step 3: Replace hard-coded strings with `buildPostCheckoutNoticeCopy(...)`**

---

## Task 3: Unit tests

**Files:**

-   Create: `src/admin/screen-licenses/postCheckoutNotice.test.js`

-   [x] **Step 1: Test intent normalization (buy / upgrade / renew / invalid)**
-   [x] **Step 2: Test license row resolution (`license_id`, AA fallback)**
-   [x] **Step 3: Test setup states (`ready`, `activate`, `download`)**
-   [x] **Step 4: Test full copy builder (spec test matrix)**

```bash
cd c:/laragon/www/monetize/wp-content/plugins/advanced-ads
npm test -- postCheckoutNotice.test.js
```

---

## Task 4: Playwright

**Files:**

-   Modify: `tests/Acceptance/Admin/Licenses/license-purchase-flow.spec.ts`
-   Modify: `tests/Acceptance/Admin/Licenses/license-expiry-notices.spec.ts`

-   [x] **Step 1: `checkout_intent=upgrade` — upgraded title, ready subtitle, params stripped**
-   [x] **Step 2: `checkout_intent=renew` — renewed title**
-   [x] **Step 3: `purchase_id` only — purchase title (backward compat)**

```bash
npm run test:playwright -- tests/Acceptance/Admin/Licenses/license-purchase-flow.spec.ts tests/Acceptance/Admin/Licenses/license-expiry-notices.spec.ts
```

---

## Task 5: i18n + docs

**Files:**

-   Modify: `languages/advanced-ads.pot`
-   Modify: `docs/superpowers/specs/2026-06-28-conditional-post-checkout-notice-design.md`

-   [x] **Step 1: Regenerate or update `.pot` for new strings**
-   [x] **Step 2: Mark spec status Implemented; check acceptance criteria**

```bash
npm run build
```

---

## Spec coverage checklist

| Spec requirement                           | Task               |
| ------------------------------------------ | ------------------ |
| Title from `checkout_intent`               | Task 1, 2          |
| Subtitle from setup state                  | Task 1, 2          |
| AA: `ready` or `download` only             | Task 1             |
| URL strip `checkout_intent` / `license_id` | Task 2             |
| Copy only — no exchange/activate changes   | Task 2 (read-only) |
| Unit test matrix                           | Task 3             |
| Playwright upgrade/renew/purchase          | Task 4             |
| `.pot` updated                             | Task 5             |

## Out of scope (do not implement)

-   Shop checkout redirect changes
-   `exchangeTokenAndSave` / shop `POST /license/activate`
-   `DownloadActivateButton.jsx` notices
-   Banner visual/styling changes
