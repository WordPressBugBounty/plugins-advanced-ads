# License class and legacy migration — design spec

**Date:** 2026-05-06  
**Scope:** `AdvancedAds\License\License` (`includes/license/class-license.php`) and compatibility with `Advanced_Ads_Admin_Licenses` (`admin/includes/class-licenses.php`), plus versioned migration via `AdvancedAds\Upgrades`.

## Goals

-   Introduce a single, well-defined **read/write surface** for license state used by new code (REST, app, future admin UI).
-   Keep **backward-compatible behavior** for existing EDD activation flows: per-addon options `{options_slug}-license-status` and `{options_slug}-license-expires`, filters `advanced_ads_license_{$options_slug}`, and comparable semantics for activation/deactivation return values where the admin class remains the orchestrator.
-   **Migrate** legacy storage (`advanced-ads-licenses` as `addon_id => license_key`) to **rich license records** (array of objects: name, status, licenseId, licenseKey, activation metadata, dates, sites, etc.) using an **exchange** HTTP endpoint.
-   **After a successful exchange**, **remove the legacy map** by deleting or emptying the legacy option so no code path treats it as authoritative.

## Non-goals (this phase)

-   Rewriting all of `Advanced_Ads_Admin_Licenses` into namespaced services in one release (optional follow-up).
-   Changing the remote EDD API contract beyond what the exchange endpoint and existing store already require.

## Current state (summary)

-   **Legacy admin:** `Advanced_Ads_Admin_Licenses` stores keys in `get_option( ADVADS_SLUG . '-licenses', [] )` (`advanced-ads-licenses`), and status/expiry in per-addon options. It handles activate/deactivate/check, firewall messaging, updater hooks, and all-access heuristics.
-   **REST:** `AdvancedAds\Rest\Licenses` persists an array under `advanced-ads-app-licenses` (`ADVADS_SLUG . '-app-licenses'`).
-   **New class (partial):** `AdvancedAds\License\License` currently uses a hybrid option shape; it must align with the **rich** model and legacy compatibility rules below.

## Data model

### Pre-migration (legacy)

-   **`advanced-ads-licenses`:** associative array `addon_slug => license_key` (only authoritative for keys until migration runs).

### Post-migration (canonical)

-   **Rich records** are the source of truth for **which keys and products** apply. Persist them in **`advanced-ads-app-licenses`** to align with the existing REST route unless a later unification moves to a single option name (if so, migrate REST in the same release to avoid two writes).
-   **`advanced-ads-licenses`:** **removed** after successful exchange — `delete_option( 'advanced-ads-licenses' )` or equivalent so the legacy map is not read.
-   **Per-addon options** `{options_slug}-license-status` and `{options_slug}-license-expires` remain the **runtime** mirror used by EDD/version checks and `any_license_valid()`-style logic until a future spec consolidates them; migration or subsequent activation **may** update them from exchange/activation results as needed.

### Identity mapping

-   Rich records use **product name** (e.g. `Tracking`, `All Access`). Core logic uses **addon id / `options_slug`** from `Data::get_addons()`.
-   The implementation **must** define an explicit mapping from exchange/product fields to addon `options_slug` (and vice versa) so a single product row resolves to the correct `{options_slug}-*` options.

## Migration (exchange) via `Upgrades`

-   Register a new entry in `AdvancedAds\Upgrades::get_updates()` pointing to a script under `upgrades/` (version bump follows existing `DB_VERSION` / `advanced_ads_db_version` conventions).
-   **Trigger:** standard upgrade pipeline (same as other `upgrade-*.php` scripts), not ad-hoc lazy hooks.
-   **Steps (conceptual):**
    1. If there is no legacy data in `advanced-ads-licenses` (empty or already migrated flag set), **skip** (idempotent).
    2. Call the **exchange** endpoint with legacy keys (and any required site/auth context).
    3. Validate response shape; on failure, **do not** delete legacy options; record failure (admin notice / log / retry token per product standards) and **do not** advance past a state that implies success.
    4. On success: write **rich** array to the **canonical** option (`advanced-ads-app-licenses` to match REST, **or** overwrite `advanced-ads-licenses` with the rich array so one option key remains — pick one in the implementation plan and update REST/`License` accordingly in the same release). Then **remove the legacy map**: either **`delete_option( 'advanced-ads-licenses' )`** (if rich lives only in `app-licenses`) **or** replace the option value so it is no longer `slug => key`.
    5. Set a **migration completed flag** (e.g. option `advanced_ads_licenses_rich_migration` or versioned marker) so reruns are no-ops.
-   **Offline / HTTP errors:** no partial wipe of legacy keys; retry on a later request or next admin load per `Framework\Updates` behavior — align with how other upgrades handle fatal vs non-fatal failures.

## `AdvancedAds\License\License` responsibilities

-   **Read API:** Resolve effective license key and display metadata for a given addon `options_slug` from **rich** store first; if migration flag indicates legacy-only era, read legacy map (pre-migration installs only).
-   **Write API:** Centralize updates to rich store when the app/REST or migration writes; avoid duplicating conflicting `status`/`expires` inside the rich blob unless they are strictly derived from or synced with `{options_slug}-license-*` options (pick **one** write path for status/expiry to prevent drift).
-   **Compatibility helpers:** `has_valid_license( $slug )`, `has_any_valid_license()` — behavior must match or intentionally document differences vs `Advanced_Ads_Admin_Licenses::any_license_valid()` (lifetime, `slider-ads` exclusion, expiry string formats).
-   **Singleton:** Keep `get()` (or equivalent) consistent with plugin patterns.

## `Advanced_Ads_Admin_Licenses` integration (recommended approach)

-   **Phase 1 (this spec):** Admin class keeps **HTTP/EDD** behavior; it **delegates** reading persisted keys and normalized license lists to `License` where that reduces duplication. After migration, **`get_licenses()`** must not assume legacy map — it should use `License` or read rich store + map to keys.
-   Preserve existing **filters**, **firewall** handling, **upgrader** messaging, and **return** conventions unless a follow-up explicitly normalizes return types.

## Error handling and security

-   Exchange requests must use **`wp_remote_post`** (or shared HTTP wrapper) with timeouts and capability checks unchanged from project standards.
-   **Secrets:** license keys in options and logs must not be written to public logs; follow WordPress and plugin security review practices.
-   **Idempotency:** Running the upgrade twice must not duplicate rows or corrupt rich array; use replace semantics or merge keyed by stable id from API.

## Testing

-   **Unit tests:** normalization helpers (legacy-only, rich-only, pre/post migration flag), mapping name → slug, idempotent migration with mocked HTTP.
-   **No live exchange** in CI — inject transport or stub response.

## Resolved decisions

1. **Migration:** Legacy licenses are converted to rich licenses via the **upgrade routine** and **exchange** endpoint.
2. **Post-success:** **Remove legacy map** — after a **successful** exchange, the site must not retain an authoritative `addon_slug => license_key` map: either delete `advanced-ads-licenses` if rich data lives elsewhere, or store the rich array under that option instead. If exchange fails, **do not** remove or overwrite legacy keys.

## Open points for implementation plan (not blockers for this spec)

-   Exact exchange URL, auth, request/response schema, and error codes.
-   Whether rich store remains **`advanced-ads-app-licenses`** only or merges with another option in the same release as REST updates.
