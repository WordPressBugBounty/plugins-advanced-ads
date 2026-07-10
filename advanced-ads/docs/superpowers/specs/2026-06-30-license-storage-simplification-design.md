# License storage simplification — design spec

**Date:** 2026-06-30  
**Status:** Approved for implementation  
**Supersedes:** [2026-06-30-license-flat-map-migration-design.md](./2026-06-30-license-flat-map-migration-design.md) (delete flat map → **transform** flat map)  
**Implementation plan:** [../plans/2026-06-30-license-storage-simplification.md](../plans/2026-06-30-license-storage-simplification.md)  
**Plugin:** `advanced-ads` (client)  
**Release:** `DB_VERSION = '2.0.9'` (not live — rewrite in-repo)

## Summary

Replace three legacy storage layers with **two options**:

| Option                      | Role                                                                    |
| --------------------------- | ----------------------------------------------------------------------- |
| `advanced-ads-app-licenses` | Account catalog — all licenses (active, expired, inactive) from shop    |
| `advanced-ads-licenses`     | **Site activation map** — per add-on `{ license, status }` on this site |

**Delete on migration:** all `{options_slug}-license-status`, `{options_slug}-license-expires`, and `advanced-ads-aa-activated-addons` (folded into activation `status`).

**Three plans, one key each:** Pro, All Access (1 site), All Access (5 sites).

## Before migration (legacy)

| Storage                            | Example                                              |
| ---------------------------------- | ---------------------------------------------------- |
| `advanced-ads-licenses`            | `{ pro: "KEY", tracking: "KEY" }` — string keys only |
| `advanced-ads-pro-license-status`  | `valid` / `invalid` / `expired`                      |
| `advanced-ads-pro-license-expires` | `lifetime` / date                                    |
| `advanced-ads-aa-activated-addons` | `[ "pro", "gam" ]`                                   |

## After migration (target)

### `advanced-ads-app-licenses` — unchanged rich shape

```php
[
  [
    'licenseKey'  => 'KEY-PRO',
    'name'        => 'Advanced Ads Pro',
    'status'      => 'active',       // account-level (shop)
    'expiryDate'  => 'lifetime',
    'sitesActivated' => 1,
    // download_url, etc.
  ],
]
```

**Source of truth for:** plan name, account status, expiry, shop metadata, validate/reconcile updates.

### `advanced-ads-licenses` — site activation map (new shape)

```php
[
  'pro' => [
    'license' => 'KEY-PRO',
    'status'  => 'active',    // activated on this site
  ],
  'tracking' => [
    'license' => 'KEY-AA',
    'status'  => 'inactive',  // AA key assigned; add-on not activated here
  ],
]
```

| Field     | Values                 | Meaning                                                  |
| --------- | ---------------------- | -------------------------------------------------------- |
| `license` | string                 | Must match a `licenseKey` in `advanced-ads-app-licenses` |
| `status`  | `active` \| `inactive` | Whether this add-on is activated **on this site**        |

**Not stored here:** expiry, account validity — read from rich row for `license`.

### Pro vs All Access

| Plan       | Activation map                                                         |
| ---------- | ---------------------------------------------------------------------- |
| Pro        | One entry (`pro`), `status: active` when licensed on site              |
| All Access | Same `license` on N add-ons; each add-on has own `active` / `inactive` |

All Access “which add-ons are activated” = add-ons where `status === 'active'` (replaces `advanced-ads-aa-activated-addons`).

## Migration

### Phase A — shop exchange (unchanged)

When rich empty or incomplete: `POST /license/exchange` per **unique** legacy key → merge into `advanced-ads-app-licenses`.

### Phase B — local transform (replaces “delete flat map”)

**Pre-condition:** `rich_covers_legacy_keys( $legacy_map, $rich )`.

**Steps:**

1. Read legacy flat map (string or nested `{ license: key }`).
2. For each add-on in map, read old `{options_slug}-license-status`:
    - `valid` → `status: 'active'`
    - anything else / missing → `status: 'inactive'`
3. **Rewrite** `advanced-ads-licenses` to activation map shape.
4. **Delete** all known `{options_slug}-license-status` and `{options_slug}-license-expires`.
5. **Delete** `advanced-ads-aa-activated-addons` (activation map is canonical).
6. Set `advanced_ads_licenses_flat_map_retired = '1'` (gate: new storage active).
7. Clear deprecated `advanced_ads_licenses_migration`.

**Does not write** `advanced-ads-app-licenses` in Phase B.

### Partial / failed migration

Until Phase B succeeds:

-   Legacy flat map + slug mirrors behave as today.
-   No activation-map reads.

## Runtime reads (after `flat_map_retired`)

| Need                         | Source                                                |
| ---------------------------- | ----------------------------------------------------- |
| User license list            | `get_licenses()` → `advanced-ads-app-licenses`        |
| EDD updater key              | `advanced-ads-licenses[addon_id].license`             |
| Add-on activated on site?    | `advanced-ads-licenses[addon_id].status === 'active'` |
| License valid / expired?     | Rich row for `licenseKey` + `is_license_entitled()`   |
| All Access activated add-ons | Filter activation map where `status === 'active'`     |

Gate: `License::is_flat_map_retired()` (same flag; meaning extended to “storage simplified”).

## Runtime writes (after `flat_map_retired`)

| Action                              | Write                                                              |
| ----------------------------------- | ------------------------------------------------------------------ |
| Shop connect / validate / reconcile | `advanced-ads-app-licenses` only                                   |
| User activates add-on on site       | `advanced-ads-licenses[addon].status = 'active'`                   |
| User deactivates add-on             | `advanced-ads-licenses[addon].status = 'inactive'` or remove entry |
| Assign key to add-on                | `advanced-ads-licenses[addon].license = key`                       |

**No writes** to `{slug}-license-status` / `{slug}-license-expires` when retired.

## Code touchpoints

| Location                                                                              | Change                                                            |
| ------------------------------------------------------------------------------------- | ----------------------------------------------------------------- |
| `License_Utils::normalize_legacy_map()`                                               | Read string keys **and** `{ license, status }` activation entries |
| New: `normalize_activation_map()`, `get_activation_map()`, `persist_activation_map()` | Activation map CRUD                                               |
| `License::maybe_retire_legacy_flat_map()`                                             | Transform + delete mirrors (not delete flat map option)           |
| `License::get_addon_key_map()`                                                        | From activation map `.license` when retired                       |
| `License::get_aa_activated_addon_ids()`                                               | From activation map `status === 'active'` when retired            |
| `License::update_license_details()`                                                   | Update activation map when retired; no mirror writes              |
| `License::sync_addon_options_from_rich()`                                             | Update activation map; skip mirror loop when retired              |
| `Advanced_Ads_Admin_Licenses::get_license_status/expires()`                           | Delegate to rich + activation map when retired                    |
| `Addon_Updater`, `classes/checks.php`                                                 | Rich expiry + activation status                                   |

## Error handling

| Scenario                                  | Behaviour                                 |
| ----------------------------------------- | ----------------------------------------- |
| Rich incomplete                           | Skip Phase B; keep legacy storage         |
| Derived keys ≠ legacy map                 | Skip Phase B; log when `WP_DEBUG`         |
| Rich row missing for activation `license` | Treat add-on as unlicensed                |
| New site (no legacy data)                 | Empty activation map; rich from shop only |

## Non-goals

-   Shop API changes
-   Changing rich row schema
-   Removing `Advanced_Ads_Admin_Licenses` EDD class (delegate only)

## Acceptance criteria

-   [ ] Phase B rewrites `advanced-ads-licenses` to `{ license, status }` per add-on
-   [ ] All `{slug}-license-status` / `{slug}-license-expires` deleted after successful Phase B
-   [ ] `advanced-ads-aa-activated-addons` deleted after Phase B
-   [ ] Post-migration: validity/expiry from rich only
-   [ ] Post-migration: site activation from `advanced-ads-licenses` only
-   [ ] Pre-migration: legacy behaviour unchanged until Phase B succeeds
-   [ ] PHPUnit green
