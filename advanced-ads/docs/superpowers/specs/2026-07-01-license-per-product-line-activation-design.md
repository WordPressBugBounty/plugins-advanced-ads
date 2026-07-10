# Per-product-line license activation — design spec

**Date:** 2026-07-01  
**Plugin:** `advanced-ads` (client)  
**Status:** Approved for implementation  
**Related:** [2026-06-29-license-activate-flow-design.md](./2026-06-29-license-activate-flow-design.md), [2026-06-30-license-storage-simplification-design.md](./2026-06-30-license-storage-simplification-design.md)

## Summary

Fix license activation when a customer owns **multiple keys of the same product line** (e.g. two Pro licenses, or All Access 2-site vs 5-site). Today activating one Pro key incorrectly shows all Pro keys as active on the site, and deactivating one affects siblings.

**Target behavior:**

-   Each license key is independent on the **License** screen.
-   **At most one key per product line** may be site-active (Pro↔Pro, All Access↔All Access, etc.).
-   **Different product lines** may be site-active together (Pro + Tracking on the same site).
-   Switching keys within a line is **shop-first**: deactivate the old key on the shop, then activate the new key.
-   The **“Active”** badge means **activated on this site** only (`sitesActivated` / site activation list), not account-level shop status.

## Decisions

| Topic                   | Decision                                                                                                                     |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| Switch within same line | Shop-first: `POST /license/deactivate` old key, then `POST /license/activate` new key                                        |
| Exclusivity scope       | Per product line via `is_same_product_line_row()`; cross-line coexistence allowed                                            |
| “Active” UI badge       | Site-only — entitled but not on this site shows inactive / available                                                         |
| Manual deactivate       | Shop-first for the selected key only; do not deactivate sibling keys or shared plugin unless no same-line key remains active |
| Implementation approach | Extend existing `License` activation pipeline (Approach 1)                                                                   |
| Shop plugin             | No changes                                                                                                                   |

## Problem

### User-visible bug

With two Pro licenses (unique keys), activating Pro 1 marks Pro 2 active as well. Deactivating Pro 1 deactivates Pro 2. Expected: only the operated key changes; activating Pro 2 should deactivate Pro 1 on this site automatically.

### Root causes

1. **`apply_manual_license_activation_on_site()`** — For single products, only All Access siblings are stripped from `sitesActivated`. Comment: _“other singles stay active together.”_
2. **`align_mutually_exclusive_site_slots()`** — Enforces one All Access winner per site, not one winner per single-product line (e.g. Pro).
3. **`normalizeShopRowStatus()` (JS)** — Returns shop `active`/`valid` without checking whether **this key** is on **this site**, so all entitled rows show “Active.”
4. **`save_licenses()` deactivate path** — Deactivating one Pro license deactivates the Pro **plugin**, affecting display/state for all Pro cards tied to addon id `pro`.

## Architecture

### Product line identity

Reuse `License::is_same_product_line_row()` / `License_Product_Map`:

-   All Access variants (2 / 5 / 10 sites) = one line.
-   Pro variants (tier suffixes) = one line → addon id `pro`.
-   Tracking, GAM, etc. = separate lines.

### Activation (switch Pro 1 → Pro 2)

```
User: Activate Pro 2
  → License::activate_on_shop_then_local( PRO-2 )
      1. find_same_line_site_active_siblings( rich, PRO-2 )
      2. For each sibling key on this site:
           request_shop_deactivate( sibling ) → merge rich
      3. request_shop_activate( PRO-2 ) → merge rich
      4. apply_manual_license_activation_on_site( rich, PRO-2 ):
           - Remove hostname from ALL same-line rows (including singles)
           - Add hostname to PRO-2 only
      5. upsert_site_activation_status: siblings inactive, PRO-2 active
      6. persist addon map (pro → PRO-2)
  → REST → UI refresh
```

On shop sibling-deactivate failure: **abort** activation; return `WP_Error` (no local-only slot change).

### Deactivation (Pro 1 only)

```
User: Deactivate Pro 1
  → License::deactivate_on_shop_then_local( PRO-1 )   [new]
      1. request_shop_deactivate( PRO-1 ) → merge rich
      2. apply_manual_license_deactivation_on_site( rich, PRO-1 )
      3. upsert_site_activation_status( PRO-1, inactive )
      4. deactivate_addon_on_site( pro ) ONLY if no other same-line key is site-active
```

Pro 2 row and key state remain unchanged.

### Mutual exclusivity matrix

| Lines                   | May coexist on one site?                             |
| ----------------------- | ---------------------------------------------------- |
| Pro + Pro               | No — one winner                                      |
| All Access + All Access | No — one winner (existing)                           |
| Pro + Tracking          | Yes                                                  |
| Pro + All Access        | Yes (existing AA vs single rules apply to addon map) |
| Tracking + Tracking     | No — one winner                                      |

### UI display contract

| Condition                                        | Badge                                              |
| ------------------------------------------------ | -------------------------------------------------- |
| Entitled + site in `sitesActivated` for this key | **Active**                                         |
| Entitled + not on this site                      | **Inactive** (even if shop row status is `active`) |
| Expired / invalid                                | Existing expired/invalid labels                    |

**JS files:** `src/admin/screen-licenses/utils.js` — `normalizeShopRowStatus()`, `getDisplayLicenseStatus()`, `isLicenseAppliedOnThisSite()`.

**Rule:** Do not treat shop account-level `active` as site-active. Do not infer activation from shared addon plugin state alone when another key of the same line could apply.

## Components

### PHP — `includes/license/class-license.php`

| Unit                                                          | Responsibility                                                                                     |
| ------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| `find_same_line_site_active_siblings( $rich, $target_key )`   | Rich rows: same product line + current site in `sitesActivated`, excluding target                  |
| `deactivate_same_line_siblings_on_shop( $rich, $target_key )` | Shop deactivate each sibling; merge responses; `WP_Error` on failure                               |
| `activate_on_shop_then_local()`                               | Call sibling shop deactivate **before** activate (extend existing)                                 |
| `deactivate_on_shop_then_local()`                             | **New** — shop deactivate then local mirror                                                        |
| `apply_manual_license_activation_on_site()`                   | Strip site from same-line siblings for singles (mirror AA branch)                                  |
| `align_mutually_exclusive_site_slots()`                       | Generalize: one site-active winner per product line (priority score)                               |
| `save_licenses()`                                             | Wire `deactivate_on_shop_then_local()` for `deactivatingLicenseKey`; conditional plugin deactivate |

### PHP — unchanged integrations

-   `License_Shop_Client::request_activate` / `request_deactivate`
-   `License_Site_Activation::upsert_status` / `get_active_license_keys`
-   REST `includes/rest/class-licenses.php` — same params; behavior change server-side

### JS — `src/admin/screen-licenses/utils.js`

-   Remove early `isRichLicenseActive(status)` return that bypasses site check.
-   Site-active display driven by `isCurrentSiteActivatedOnLicense()` per row.
-   `isLicenseAppliedOnThisSite()` — key-specific; fix shared-`pro` addon false positives.

## Data flow (unchanged storage)

```
advanced-ads-app-licenses   rich rows + sitesActivated[] per licenseKey
advanced-ads-licenses       [{ license, status }] per unique key on site
```

Activation/deactivation still flows through `License::save_licenses()` and shop REST.

## Error handling

| Case                                         | Behavior                                                                |
| -------------------------------------------- | ----------------------------------------------------------------------- |
| Sibling shop deactivate fails before switch  | Return error; do not activate new key                                   |
| Shop activate fails after sibling deactivate | Surface error; persist merged shop response; no silent local activation |
| Deactivate shop failure                      | Return error; keep prior local state                                    |
| User owns one key only                       | No sibling step; activate/deactivate as today                           |

## Testing

### PHPUnit (`tests/Unit/License/LicenseTest.php`)

-   [ ] Two Pro keys A & B: activate B → A off site, B on site, site list A=inactive B=active
-   [ ] Deactivate A while B inactive → B unchanged
-   [ ] Pro A + Tracking C both site-active after separate activates
-   [ ] Two All Access keys: activate one → other stripped (regression)
-   [ ] Sibling shop deactivate failure blocks activation (`WP_Error`)
-   [ ] `align_mutually_exclusive_site_slots` keeps highest-priority key per line

### Playwright (`tests/Acceptance/Admin/Licenses/license-per-product-line.spec.ts`)

-   [ ] Shop `active` + no site → display inactive
-   [ ] Site activated → display active
-   [ ] Two Pro rows: only keyed row shows active

### Manual

-   [ ] Two Pro licenses in account: activate/deactivate independently per card
-   [ ] Switch Pro 1 → Pro 2: only Pro 2 active; shop activation count correct
-   [ ] Pro + Tracking both active on site

## Out of scope

-   Shop plugin / EDD API changes
-   Auto-selecting “best” Pro on connect when user owns several (existing auto-activate only)
-   All Access per-addon activation UX changes
-   Retiring `sitesActivated` in favor of local-only storage

## Implementation plan

[../plans/2026-07-01-license-per-product-line-activation.md](../plans/2026-07-01-license-per-product-line-activation.md)
