# License flat map migration — design spec

> **Superseded by [2026-06-30-license-storage-simplification-design.md](./2026-06-30-license-storage-simplification-design.md)** — do not delete `advanced-ads-licenses`; transform to activation map and delete slug mirrors instead.

**Date:** 2026-06-30  
**Status:** Superseded  
**Implementation plan:** [../plans/2026-06-30-license-flat-map-migration.md](../plans/2026-06-30-license-flat-map-migration.md)  
**Plugin:** `advanced-ads` (client)

## Summary

Retire the legacy `advanced-ads-licenses` flat map (`{ pro: 'key', gam: 'key', … }`) and use **`advanced-ads-app-licenses`** as the single source of truth for license keys, status, and expiry.

**Three plans, one key each:**

| Plan                 | License key covers       |
| -------------------- | ------------------------ |
| Pro                  | Pro add-on only          |
| All Access (1 site)  | All add-ons on this site |
| All Access (5 sites) | All add-ons on this site |

Migration does **not** write per-addon `{options_slug}-license-status` / `{options_slug}-license-expires` options. Those legacy EDD mirrors are out of scope for this migration; runtime reads status and expiry from rich rows.

## Hard constraint

**Phase B** does not write `advanced-ads-app-licenses`. **Phase A** (upgrade exchange) writes rich rows from the shop.

| Phase                   | Writes `advanced-ads-app-licenses`?     |
| ----------------------- | --------------------------------------- |
| A — shop exchange       | **Yes** (one row per unique legacy key) |
| B — flat map retirement | **No** (read only)                      |

## Decisions

| Topic                            | Decision                                                               |
| -------------------------------- | ---------------------------------------------------------------------- |
| `advanced-ads-app-licenses`      | **Canonical** — keys, status, expiry                                   |
| Per-addon status/expiry options  | **Not synced** during migration                                        |
| Flat map `advanced-ads-licenses` | **Delete** when rich covers all unique legacy keys                     |
| Rich bootstrap                   | **Upgrade Phase A** — `POST /license/exchange` per unique legacy key   |
| Upgrade Phase B                  | **Local only** — bootstrap AA add-ons, delete flat map                 |
| `upgrade-2.0.9.php`              | **Not live** — rewrite in-repo before release (`DB_VERSION = '2.0.9'`) |
| Retirement flag                  | `advanced_ads_licenses_flat_map_retired = '1'`                         |
| Old flag                         | `advanced_ads_licenses_migration` deprecated; cleared on retirement    |

## Problem

### Legacy storage (pre-migration)

| Option                           | Example                           | Role                                  |
| -------------------------------- | --------------------------------- | ------------------------------------- |
| `advanced-ads-licenses`          | `{ pro: 'KEY1', gam: 'KEY1', … }` | Per-addon license keys (flat map)     |
| `{options_slug}-license-status`  | `valid`                           | Legacy EDD (not updated by migration) |
| `{options_slug}-license-expires` | date / `lifetime`                 | Legacy EDD (not updated by migration) |

### Target storage (post-migration)

| Option                             | Role                                               |
| ---------------------------------- | -------------------------------------------------- |
| `advanced-ads-app-licenses`        | **Canonical** — plan rows with key, status, expiry |
| `advanced-ads-aa-activated-addons` | Bootstrapped from legacy map + rich for All Access |
| `advanced-ads-licenses`            | **Removed** when retirement succeeds               |

## Architecture

### Migration flow

```
┌──────────────────────────────────────────────────────────────┐
│  Phase A — shop exchange (upgrade / license screen)          │
├──────────────────────────────────────────────────────────────┤
│  foreach unique legacy key → POST /license/exchange          │
│  merge rows → advanced-ads-app-licenses                      │
└──────────────────────────────────────────────────────────────┘
                              ↓
┌──────────────────────────────────────────────────────────────┐
│  Phase B — local retirement                                  │
├──────────────────────────────────────────────────────────────┤
│  $rich = get_licenses()          // READ only                │
│  $map  = advanced-ads-licenses                               │
│  if ! rich_covers_legacy_keys( unique keys ) → SKIP          │
│  bootstrap aa-activated-addons (legacy map + rich read)      │
│  delete advanced-ads-licenses                                │
│  set flat_map_retired                                        │
└──────────────────────────────────────────────────────────────┘
```

### Read path after retirement

```
advanced-ads-app-licenses  (read)
  → resolve_addon_license_assignments()
  → build_persisted_addon_key_map_from_rich()   // in-memory
  → Addon_Updater / EDD_Updater per add-on
```

## Flat map retirement

**Method:** `License::maybe_retire_legacy_flat_map( $rich, $map )`

**Inputs:** `$rich` from `get_licenses()` — must not be modified or persisted.

1. Skip if `flat_map_retired` or legacy map empty.
2. Skip if `$rich` empty or `! rich_covers_legacy_keys( $map, $rich )` (unique keys only).
3. Safety check: derived addon keys from rich match stored flat map per add-on. On mismatch: log, skip delete.
4. `bootstrap_aa_activated_addons_from_legacy_map( $map, $rich )`.
5. `delete_option( 'advanced-ads-licenses' )`
6. `update_option( 'advanced_ads_licenses_flat_map_retired', '1' )`
7. `delete_option( 'advanced_ads_licenses_migration' )`

**No** `update_option( OPTION_RICH, … )` or per-addon mirror writes in this path.

## Upgrade `2.0.9.php`

```php
if flat_map_retired → return
$map = normalize_legacy_map( advanced-ads-licenses )
if map empty → set flat_map_retired, return
maybe_complete_legacy_license_migration()  // exchange if needed, then retire
schedule_license_expiry if retired
```

## Error handling

| Scenario                         | Behaviour                                                   |
| -------------------------------- | ----------------------------------------------------------- |
| Rich empty at upgrade            | Exchange each unique legacy key; Phase B when rich complete |
| Rich missing a unique legacy key | Skip retirement; keep flat map                              |
| Derived map ≠ stored flat map    | Skip delete; log when `WP_DEBUG`                            |
| Rich-only site, no legacy map    | Set `flat_map_retired`; no-op                               |

## Edge cases

| State                                          | Result                                                 |
| ---------------------------------------------- | ------------------------------------------------------ |
| All Access: same key on N addons in legacy map | One rich row; bootstrap `aa-activated-addons` from map |
| Pro only                                       | One key in map and rich                                |
| `slider-ads`                                   | Excluded (existing behaviour)                          |

## Non-goals

-   Syncing `{options_slug}-license-status` / `{options_slug}-license-expires` during migration.
-   Removing legacy EDD mirror options from the codebase (separate work).
-   Shop plugin changes.

## Acceptance criteria

-   [ ] Migration never writes per-addon `-license-status` / `-license-expires`
-   [ ] `advanced-ads-licenses` deleted only when rich covers all unique legacy keys
-   [ ] `Addon_Updater` uses derived keys after retirement
-   [ ] PHPUnit green
