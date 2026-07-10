# Remove Settings Licenses Tab — design spec

**Date:** 2026-07-01  
**Plugin:** `advanced-ads` (client)  
**Status:** Approved for implementation  
**Related:** [2026-06-29-license-activate-flow-design.md](./2026-06-29-license-activate-flow-design.md), [2026-06-30-license-storage-simplification-design.md](./2026-06-30-license-storage-simplification-design.md)

## Summary

Remove the legacy **Settings → Licenses** tab and all UI-only activation paths. License management remains exclusively on the React **License** screen (`admin.php?page=advanced-ads-app&path=/license`) backed by REST (`/advanced-ads/v1/licenses`) and `AdvancedAds\License\License`.

Backend license storage, shop activation, EDD updater registration, and status checks are unchanged.

## Decisions

| Topic                                                       | Decision                                                                                                                            |
| ----------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Old URL `admin.php?page=advanced-ads-settings#top#licenses` | **No redirect** — opens Settings without a Licenses tab                                                                             |
| Cleanup depth                                               | **Full dead-path removal** — tab UI, AJAX, JS handlers, view templates, and unused `Advanced_Ads_Admin_Licenses` activation methods |
| Admin URL for notices/links                                 | **Shared helper** `License_Utils::admin_screen_url()` (Approach 1)                                                                  |
| `Advanced_Ads_Admin_Licenses` class                         | **Keep trimmed** — status, expiry, updater hooks, `any_license_valid()`; remove UI-only activate/deactivate                         |
| Storage options                                             | **Unchanged** — `advanced-ads-app-licenses`, `advanced-ads-licenses` still written by REST/shop flows                               |
| Third-party `advanced_ads_license_{$slug}` filter           | **Removed with AJAX path** — was only invoked from old activate/deactivate handlers                                                 |
| i18n `.pot` cleanup                                         | **Out of scope** — regenerated on next i18n pass                                                                                    |

## Problem

Two license UIs coexist:

1. **Legacy:** Settings tab with per-addon key fields, form POST + AJAX (`advads-activate-license` / `advads-deactivate-license`) via `Advanced_Ads_Admin_Licenses`.
2. **Current:** Dedicated License screen with REST API and shop-first activation.

The legacy tab duplicates functionality, confuses users, and maintains ~400 lines of dead-path code (EDD direct activation bypassing the shop REST contract). The new screen is the sole supported management UI.

## Architecture

### Before

```
Settings page
  └── Licenses tab
        ├── Addon_Updater::add_license_fields() → setting-license.php
        ├── AJAX activate/deactivate → Advanced_Ads_Admin_Licenses (EDD API)
        └── register_setting('advanced-ads-licenses') form save

License screen (parallel)
  └── REST → License::save_licenses() → shop activate → options
```

### After

```
Settings page (no Licenses tab)

License screen (sole UI)
  └── REST → License::save_licenses() → shop activate → options
        └── Addon_Updater reads key map → EDD updaters

Notices / plugin-list warnings → License_Utils::admin_screen_url()
License_Admin_Post redirects     → License_Utils::admin_screen_url()
```

## Components

### Add: `License_Utils::admin_screen_url()`

**File:** `includes/license/utils.php`

```php
/**
 * License admin screen URL with optional query args.
 *
 * @param array<string, int|string> $query_args Query arguments.
 * @return string
 */
public static function admin_screen_url( array $query_args = [] ): string
```

Builds `admin.php?page=advanced-ads-app&path=/license` (same as `License_Admin_Post::license_admin_url()` today). Refactor `License_Admin_Post` to call this helper instead of its private duplicate.

### Modify: `includes/admin/class-settings.php`

Remove:

-   `ADVADS_SETTINGS_LICENSES` constant
-   Licenses entry from `add_tabs()`
-   `register_setting( self::ADVADS_SETTINGS_LICENSES, … )`
-   `section_licenses()` and both render callbacks
-   Licenses entry from `allow_save_settings()`

### Modify: `includes/admin/class-addon-updater.php`

Remove:

-   `add_action( 'advanced-ads-settings-init', [ $this, 'add_license_fields' ], 99 )`
-   `add_license_fields()` method
-   `render_license_field()` method

Update `add_plugin_list_license_notice()` link to `License_Utils::admin_screen_url()`.

### Modify: `views/admin/screens/settings.php`

Remove special-case that hides the save button when group is `advanced-ads-licenses`:

```php
if ( isset( $_setting_tab['group'] ) && 'advanced-ads-licenses' !== $_setting_tab['group'] ) {
```

Replace with unconditional submit button when group is set (same as other tabs).

### Modify: `includes/admin/class-ajax.php`

Remove:

-   `wp_ajax_advads-activate-license` action + `activate_license()` method
-   `wp_ajax_advads-deactivate-license` action + `deactivate_license()` method
-   `use Advanced_Ads_Admin_Licenses` import if no longer needed

### Modify: `admin/assets/js/admin.js`

Remove (~lines 183–340):

-   License key blur auto-copy handler
-   `.advads-license-activate` click handler
-   `.advads-license-deactivate` click handler
-   `advads_disable_license_buttons()` helper

### Modify: `admin/includes/class-licenses.php`

Remove UI-only methods (only callers were AJAX handlers):

-   `activate_license()`
-   `deactivate_license()`
-   `check_license()` (deprecated, no external callers)
-   `blocked_by_firewall()` (private to activate/deactivate)
-   `shortcuit_deactivation()` (private to deactivate)

**Keep:**

-   `addon_upgrade_filter()`, `update_license_after_version_info()`
-   `get_licenses()`, `save_licenses()`, `get_license_status()`, `get_license_expires()`
-   `any_license_valid()`, `get_probably_all_access()`, `get_probably_all_access_expiry()`
-   `get_installed_add_on_by_key()`, `get_filtered_licenses()`, `clear_license_cache()`

### Update links (3 call sites → `License_Utils::admin_screen_url()`)

| File                                     | Current target                                      |
| ---------------------------------------- | --------------------------------------------------- |
| `admin/includes/notices.php`             | `admin.php?page=advanced-ads-settings#top#licenses` |
| `admin/includes/ad-health-notices.php`   | same                                                |
| `includes/admin/class-addon-updater.php` | same                                                |

### Delete files

| File                                            | Reason                                            |
| ----------------------------------------------- | ------------------------------------------------- |
| `admin/views/setting-license.php`               | Only rendered by removed `render_license_field()` |
| `views/admin/settings/license/section-help.php` | Only included by removed settings section         |
| `views/admin/settings/license/addon-box.php`    | Orphaned — no includes in codebase                |

## Data flow (unchanged)

```
License screen (React)
  → GET/POST /advanced-ads/v1/licenses
  → License::save_licenses() / License_Shop_Client::request_shop_activate()
  → advanced-ads-app-licenses (rich catalog)
  → advanced-ads-licenses (site activation map)
  → Addon_Updater::add_on_updater() reads key map → EDD_Updater per add-on
```

Removing the Settings tab does not alter read/write paths for license data.

## Edge cases

| Case                                  | Expected behavior                                                                                           |
| ------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Bookmarked `#top#licenses`            | Settings loads; Licenses tab absent; no redirect                                                            |
| Invalid license on plugins list       | Warning links to License screen                                                                             |
| Admin notice “add valid license keys” | Links to License screen                                                                                     |
| Post-checkout / connect redirect      | `License_Admin_Post` still redirects to License screen (via shared helper)                                  |
| Ad Admin role saving settings         | `advanced-ads-licenses` option group removed from allowed options — no impact; licenses saved via REST only |
| Multisite plugin-list warnings        | Unchanged — `plugin_licenses_warning()` already skips multisite                                             |

## Testing

### Automated

-   Run existing unit tests: `tests/Unit/License/LicenseTest.php`, `tests/Unit/Admin/License_Admin_Post_Test.php`
-   No new unit tests required unless a small test for `License_Utils::admin_screen_url()` is added

### Manual checklist

-   [ ] Settings page has no Licenses tab
-   [ ] License screen loads; connect / activate / deactivate add-on works
-   [ ] Plugin list invalid-license row links to License screen
-   [ ] Admin notices about missing keys link to License screen
-   [ ] Add-on updates download with valid license key
-   [ ] `classes/checks.php` license validation still works

## Out of scope

-   Retiring `Advanced_Ads_Admin_Licenses` entirely (future migration to `License` class)
-   Redirect from old Settings Licenses URL
-   Removing per-addon `{slug}-license-status` / `{slug}-license-expires` options (storage simplification track)
-   `.pot` / `.po` string cleanup

## Implementation plan

[../plans/2026-07-01-remove-settings-licenses-tab.md](../plans/2026-07-01-remove-settings-licenses-tab.md)
