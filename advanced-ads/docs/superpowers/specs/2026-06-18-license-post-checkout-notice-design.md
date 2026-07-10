# License screen notices — UI & content design

**Date:** 2026-06-18  
**Status:** Draft — awaiting review  
**Scope:** UI and content only (no trigger/timing/URL logic changes)

## Goal

Unify all license-screen dismissible banners to match approved screenshots using **Tailwind-only** styling. Expiry and shop-error notices use the same **warning-orange** style as activation error (triangle icon, bordered banner) — **body copy unchanged**.

## Constraints

-   **Tailwind only** — no new CSS/SCSS files.
-   **No commits** — left uncommitted until the developer commits manually.
-   **No behavior changes** — keep `@wordpress/notices` store, triggers, URL stripping, dedup, auto-dismiss timer (6.5s on activation notices).
-   **Shared render component** — one `LicenseNoticeBanner` in `LicenseNotices.jsx` renders every notice in context `advanced-ads/licenses`.

---

## Notice inventory

| #   | Case                  | Trigger                                           | Source file                  | Screenshot                                       |
| --- | --------------------- | ------------------------------------------------- | ---------------------------- | ------------------------------------------------ |
| 1   | Post-checkout success | `?purchase_id=` in URL                            | `LicenseNotices.jsx`         | Green, no icon                                   |
| 2   | Activation success    | "Download and activate" completes                 | `DownloadActivateButton.jsx` | Green, checkmark icon                            |
| 3   | Activation error      | "Download and activate" fails                     | `DownloadActivateButton.jsx` | Orange/warning, triangle icon                    |
| 4   | Expiry warning        | License expiring soon                             | `LicenseNotices.jsx`         | Warning-orange (same style as case 3), body only |
| 5   | Expiry expired        | License expired                                   | `LicenseNotices.jsx`         | Warning-orange (same style as case 3), body only |
| 6   | Shop query errors     | `?advads_upgrade_error=` / `?advads_renew_error=` | `LicenseNotices.jsx`         | Warning-orange (same style as case 3), body only |

---

## Content specification

### 1. Post-checkout success

| Field     | Copy                                                                          |
| --------- | ----------------------------------------------------------------------------- |
| **Title** | License purchased successfully                                                |
| **Body**  | Your license is ready. Use 'Download and activate' to set it up on this site. |

Use straight single quotes around `Download and activate`.

### 2. Activation success

| Field     | Copy                                                       |
| --------- | ---------------------------------------------------------- |
| **Title** | All set                                                    |
| **Body**  | Your license and plugin are fully set up and ready to use. |

**Replaces current copy:** title wrongly reuses "License purchased successfully"; body uses API message or "License activated." / install progress text.

### 3. Activation error

| Field     | Copy                                                                                                                                                      |
| --------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Title** | Automatic setup didn't complete                                                                                                                           |
| **Body**  | We couldn't activate the plugin automatically. Your license is active — please activate the plugin manually by clicking **Download and activate** button. |

-   `Download and activate` is **bold** in the body (JSX `<strong>`, not HTML string).
-   **Copy note:** Screenshot shows typo "y clicking" — use **"by clicking"** in implementation unless design confirms the typo is intentional.

**Replaces current copy:** body currently shows the raw API/exception message (e.g. "Invalid license.").

### 4–6. Expiry & shop error notices (unchanged body copy)

Same **warning-orange** visual style as case 3 (`TriangleAlert` icon, orange border/background). **No title** — existing strings become the body only.

| Case               | Body (unchanged)                                                                                                                               |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Expiry expired     | `%s has expired. Renew to restore updates and support.`                                                                                        |
| Expiry warning     | `%1$s expires in %2$s days. Renew to avoid interruption.`                                                                                      |
| Shop upgrade error | `This license could not be upgraded to the selected plan. Ensure upgrade paths are configured in the shop (Pro → All Access), then try again.` |
| Shop renew error   | `This license could not be renewed. It may not be expired, renewals may be disabled on the shop, or the license may be lifetime.`              |

---

## UI specification

### Shared banner layout

All notices share this flex layout (title row omitted when no `title`):

```
┌────────────────────────────────────────────────────────────────┐
│ [icon?]  Title (bold)                                       ✕  │
│          Body text                                             │
└────────────────────────────────────────────────────────────────┘
```

| Element    | Tailwind                                                                  |
| ---------- | ------------------------------------------------------------------------- |
| Outer      | `advads-license-notice relative mb-6 flex gap-3 rounded border p-4 pr-10` |
| Icon slot  | `shrink-0 size-5 mt-0.5` (hidden when no icon)                            |
| Text block | `min-w-0 flex-1`                                                          |
| Title      | `font-semibold text-sm leading-snug m-0`                                  |
| Body       | `text-sm leading-snug m-0 mt-0.5`                                         |
| Dismiss    | `absolute top-3 right-3 p-1 leading-none opacity-70 hover:opacity-100`    |

Icons via `lucide-react` (already used on license screen): `CircleCheck` (success), `TriangleAlert` (warning).

### Variant: success-green (cases 1 & 2)

| Property  | Tailwind                                                  |
| --------- | --------------------------------------------------------- |
| Container | `border-green-200 bg-green-50 text-green-800`             |
| Icon      | Case 1: **none**. Case 2: `CircleCheck`, `text-green-800` |
| Dismiss   | `text-green-800`                                          |

### Variant: warning-orange (cases 3–6)

| Property  | Tailwind                                             |
| --------- | ---------------------------------------------------- |
| Container | `border-orange-200 bg-orange-50 text-orange-950`     |
| Icon      | `TriangleAlert`, `text-orange-950`                   |
| Dismiss   | `text-orange-950`                                    |
| Title     | Case 3 only — cases 4–6 are body-only (no title row) |

---

## Notice metadata contract

Extend notice options passed to `createSuccessNotice` / `createErrorNotice`:

```js
{
  id,
  type: 'default',
  context: LICENSE_NOTICES_CONTEXT,
  isDismissible: true,
  // New display fields:
  title: '…',           // optional — omit for single-line notices
  message: '…',         // plain string OR omit when using messageContent
  messageContent: <…/>, // optional JSX for bold inline spans (case 3)
  icon: 'none' | 'success' | 'warning',  // default 'none'
  variant: 'success' | 'warning', // drives color classes
}
```

`LicenseNotices` render logic:

1. Read `title`, `message` / `messageContent`, `icon`, `variant` from notice options (fallback `message` → `notice.content`).
2. Render title row only when `title` is set.
3. Always render body from `messageContent` or `message`.
4. Map `variant`: `'warning'` → orange, `'success'` → green; default `'warning'` when `notice.status === 'error'` and no variant set.

Remove all `__unstableHTML` usage.

---

## Implementation plan

### `LicenseNotices.jsx`

1. Remove `import { Notice } from '@wordpress/components'`.
2. Add `LicenseNoticeBanner` — Tailwind + `cn()` from `@admin/utils`, lucide icons.
3. Render all dismissible notices through the banner.
4. Update `handlePostCheckoutNotice` — pass `title`, `message`, `icon: 'none'`, `variant: 'success'`; remove `__unstableHTML`.
5. Expiry/shop notices — pass `icon: 'warning'`, `variant: 'warning'`, `message` only (no title; unchanged strings).

### `DownloadActivateButton.jsx`

1. Update `showNotice()`:
    - **Success:** `title: 'All set'`, fixed body string, `icon: 'success'`, `variant: 'success'`.
    - **Error:** `title: "Automatic setup didn't complete"`, fixed body with JSX bold, `icon: 'warning'`, `variant: 'warning'`, use `createErrorNotice` (or keep status mapping — banner uses `variant` for colors, not WP status alone).
2. Remove `__unstableHTML`.
3. Keep 6.5s auto-dismiss timer unchanged.

### Files not changed

-   `License.jsx`, PHP, API hooks, notice triggers.
-   No new CSS files.

### Build

Developer runs `npm run build` locally after edits.

---

## Test impact

| Test                                 | Current assertion                                                           | After change                                                  |
| ------------------------------------ | --------------------------------------------------------------------------- | ------------------------------------------------------------- |
| `activate-license.spec.ts` success   | Title "License purchased successfully", body contains install progress text | Title **"All set"**, body **"fully set up and ready to use"** |
| `activate-license.spec.ts` error 403 | Title + "Invalid license."                                                  | Title + fixed body (no API message)                           |
| `license-purchase-flow.spec.ts`      | "License purchased successfully"                                            | Unchanged                                                     |
| `licenseNotice()` helper             | Filters `.components-notice`                                                | Update to `.advads-license-notice`                            |

Playwright updates are a follow-up when implementing — note only, not in scope unless requested.

---

## Acceptance criteria

-   [ ] All three screenshot cases match visually (colors, icons, typography, dismiss)
-   [ ] Copy matches spec (single quotes on case 1; "by clicking" on case 3)
-   [ ] Tailwind only — no new CSS files
-   [ ] No change to when/why notices appear or auto-dismiss timing
-   [ ] Dismiss works on all variants
-   [ ] Expiry/shop notices render in warning-orange style with unchanged body copy
-   [ ] `advads-license-notice` class present for test hooks

---

## Open question

**Case 3 typo:** Screenshot reads "manually **y** clicking" — confirm **"by clicking"** before implementation.
