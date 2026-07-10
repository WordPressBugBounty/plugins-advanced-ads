# License Activate Flow — Legacy vs New User

**Date:** 2026-06-29  
**Related:** Admin-post license exchange spec (`advanced-ads-licenses` repo, `2026-06-29-admin-post-license-exchange-design.md`)  
**Plugin:** `advanced-ads` (client) — shop REST unchanged  
**Status:** Implemented  
**Plan:** [../plans/2026-06-29-license-activate-flow.md](../plans/2026-06-29-license-activate-flow.md)

## Summary

Clarify and enforce license activation rules across all admin-post token exchange paths (connect, buy, upgrade, renew). Activation is always **shop-first**: call shop `POST /license/activate`; only update local `sitesActivated` and legacy addon map on success; on failure, leave local activation unchanged and show an error.

**Legacy users** keep existing local activations and sync them to the shop when missing. **New users** (empty store) auto-activate the highest-priority entitled license on connect, or the checkout license on buy. Manual license switching via the UI is unchanged — verify only.

## Decisions

| Topic               | Decision                                                                                                                         |
| ------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| Legacy vs new       | **Legacy** = rich licenses (`advanced-ads-app-licenses`) or flat map (`advanced-ads-licenses`) has ≥1 key. **New** = both empty. |
| Flows in scope      | All admin-post exchange paths: connect, buy, upgrade, renew                                                                      |
| Activation contract | Shop REST activate first → local only on success → error on failure                                                              |
| New user connect    | Auto-activate highest-priority entitled license (`maybe_activate_licenses_for_current_site` scoring)                             |
| New user buy        | Shop activate checkout `license_id` row, then mark active locally                                                                |
| Legacy connect      | Preserve all local activations; sync each locally-active license missing on shop (shop-first)                                    |
| Legacy buy          | Activate new purchase only; do not demote existing active licenses                                                               |
| Manual switch       | Existing `DownloadActivateButton` → `activateLicenseOnShop()` — verify, no UI changes                                            |
| Implementation      | Centralize in `License::save_licenses()` + shared shop-first helper (Approach 1)                                                 |
| Shop plugin         | No changes                                                                                                                       |

## Problem

Today activation behavior is inconsistent:

1. **`ensure_site_slots_match_active_assignments()`** can call `apply_manual_license_activation_on_site()` without a successful shop activate — violates shop-first.
2. **Legacy users** skip `should_run_shop_auto_activate()` when the flat map exists, but connect exchange does not sync locally-active licenses that are missing on the shop.
3. **New users** mostly work via `maybe_activate_licenses_for_current_site()`, but the shop-first contract is not enforced uniformly across PHP paths.
4. **Manual UI** already calls shop before save; needs verification only.

## Activation contract

```
POST shop /wp-json/advanced-ads/v2/license/activate { license, site }
  → 2xx: merge returned row(s), apply local sitesActivated + legacy map
  → error: do NOT change local activation for that key; surface error to user
```

Applies to:

-   New user auto-activate (connect)
-   Post-checkout activate (`License_Admin_Post`)
-   Legacy connect shop sync
-   Manual UI button (already shop-first via `licenses-api.js`)
-   PHP auto paths (to be fixed)

## Architecture

```
admin_post_advanced-ads-license
  → License_Exchange::request_by_token()
  → [optional] checkout shop activate (license_id)
  → License::save_licenses( $rows, $activate_new, $activating_key )
      → merge incoming rows
      → branch: is_legacy_store() vs new store
      → legacy connect: sync_local_activations_to_shop()  [shop-first per key]
      → new / buy / upgrade: maybe_activate_licenses_for_current_site()  [shop-first]
      → reconcile_persisted_licenses()
      → sync addon options + legacy map
  → redirect license screen (+ error query arg on activation failure)
```

### New helper: `License::activate_on_shop_then_local()`

| Input | `string $license_key`, `array $rich` (by reference) |
| Output | `array\|WP_Error` — updated rich list or error |
| Steps | `request_shop_activate()` → merge rows → `apply_manual_license_activation_on_site()` only after shop success |

Replace direct `apply_manual_license_activation_on_site()` calls inside auto-activate paths with this helper.

### New helper: `License::is_legacy_license_store()`

Returns true when `has_stored_licenses()` OR `has_stored_legacy_license_map()` is true **before** merging incoming exchange rows (caller passes pre-merge state).

### New helper: `License::sync_local_activations_to_shop()`

For legacy connect only. Finds license keys that are:

-   Active locally (`resolve_addon_license_assignments()` or `sitesActivated` / legacy map), AND
-   Not activated on shop for current hostname in incoming exchange rows

For each key where the license is assigned locally (legacy map or `resolve_addon_license_assignments`) but `sitesActivated` lacks this hostname: `activate_on_shop_then_local()`. Stop on first `WP_Error` and return it. Skip keys already listing this site in `sitesActivated`.

## Per-flow behavior

### New user (empty store)

| Flow        | Behavior                                                                                            |
| ----------- | --------------------------------------------------------------------------------------------------- |
| **Connect** | Exchange → `maybe_activate_licenses_for_current_site()` (highest priority, shop-first)              |
| **Buy**     | Exchange → shop activate `license_id` in `License_Admin_Post` → `save_licenses` with activating key |
| **Upgrade** | Exchange → shop activate successor → migrate predecessor slot                                       |
| **Renew**   | Exchange → metadata sync; license usually already shop-active                                       |

### Legacy user (stored licenses or flat map)

| Flow        | Behavior                                                                                   |
| ----------- | ------------------------------------------------------------------------------------------ |
| **Connect** | Exchange merge → preserve local activations → `sync_local_activations_to_shop()` for drift |
| **Buy**     | Exchange → shop activate new `license_id` only; existing activations untouched             |
| **Upgrade** | Existing successor migration (shop-first)                                                  |
| **Renew**   | Metadata sync only                                                                         |

Legacy connect does **not** auto-activate a different/higher-priority license.

### Manual switch (verify only)

Flow: `DownloadActivateButton` → `activateLicenseOnShop()` → `setLicensesAndSave(licenses, { activatingLicenseKey })`.

Verify:

-   Shop `POST /license/activate` runs before plugin REST save
-   Shop failure shows `"Automatic setup didn't complete"` notice
-   `save_licenses()` with `activating_license_key` does not call shop again (JS already did)

## `save_licenses()` changes

After merge, before `reconcile_persisted_licenses()`:

```php
$is_legacy = self::is_legacy_license_store( $existing );

if ( $is_legacy && ! $activate_new && '' === trim( $activating_license_key ) ) {
    // Legacy connect / renew metadata sync
    $sync = self::sync_local_activations_to_shop( $merged );
    if ( is_wp_error( $sync ) ) {
        return $sync;
    }
    $merged = $sync;
} elseif ( ! $is_legacy && ( $was_empty || $activate_new ) ) {
    // New user paths — existing auto-activate flags, shop-first enforced in maybe_activate_*
}
```

**Fix `ensure_site_slots_match_active_assignments()`:** replace bare `apply_manual_license_activation_on_site()` with `activate_on_shop_then_local()`. If shop returns error, skip that key (do not add local slot).

**`should_run_shop_auto_activate()`:** unchanged — legacy map still blocks blind auto-activate on new keys; legacy connect uses explicit sync instead.

## Error handling

| Case                                 | Behavior                                                                                              |
| ------------------------------------ | ----------------------------------------------------------------------------------------------------- |
| Shop activate HTTP/network error     | Return `WP_Error` from `save_licenses()`; admin-post redirects with `advads_activation_error=network` |
| Shop 4xx (invalid, at limit)         | `advads_activation_error=activate_failed` + optional `advads_activation_message` (sanitized)          |
| Legacy connect: one of N syncs fails | Fail on first error; prior successful syncs merged; error redirect                                    |
| Manual UI shop failure               | Existing error notice — no local save                                                                 |
| Exchange succeeds, activation fails  | License rows still saved (metadata); user sees error notice on license screen                         |

### New query args (license screen)

| Arg                         | Meaning                                                |
| --------------------------- | ------------------------------------------------------ |
| `advads_activation_error`   | `network`, `activate_failed`, `forbidden`              |
| `advads_activation_message` | Optional shop error message (sanitized, max 200 chars) |

`LicenseNotices.jsx` handles these (mirror `advads_exchange_error` pattern).

## Security

-   Unchanged: shop activate requires valid license key + site hostname
-   Admin-post capability check unchanged
-   Error messages sanitized before query arg redirect

## Testing

### PHPUnit — `tests/Unit/License/LicenseTest.php`

-   `activate_on_shop_then_local()` — shop success → local `sitesActivated` updated
-   `activate_on_shop_then_local()` — shop 403 → no local `sitesActivated` change
-   `ensure_site_slots_match_active_assignments()` — no longer activates locally without shop mock success
-   `sync_local_activations_to_shop()` — locally active Pro, shop row missing site → shop called
-   `sync_local_activations_to_shop()` — already on shop → no shop call
-   Legacy connect path in `save_licenses()` — does not auto-activate unrelated license
-   New empty store connect — highest-priority license shop-activated

### PHPUnit — `tests/Unit/Admin/LicenseAdminPostTest.php` (if present)

-   Activation failure during save → redirect includes `advads_activation_error`
-   Successful buy → no activation error arg

### Playwright — `tests/Acceptance/Admin/Licenses/activate-license.spec.ts`

-   Manual activate: shop mock 200 → success notice (verify only)
-   Manual activate: shop mock 403 → error notice, no local activation change

No shop plugin changes or new Playwright flows for admin-post activation errors (covered by PHPUnit).

## Out of scope

-   Shop REST schema changes
-   Changing manual switch UX or All Access add-on picker
-   Auto-activating multiple unrelated licenses on legacy connect
-   JavaScript shop-first refactor (already correct)

## Verification checklist

-   [ ] New site connect → highest-priority license shop-activated and shown active in UI
-   [ ] New site buy → purchased license active after admin-post redirect
-   [ ] Legacy site connect → existing Pro/Tracking assignments preserved; shop sync when drifted
-   [ ] Legacy site buy additional license → new license active; old licenses unchanged
-   [ ] Shop activate failure → error notice; no phantom local activation
-   [ ] Manual Download and activate → shop-first (unchanged behavior confirmed)
