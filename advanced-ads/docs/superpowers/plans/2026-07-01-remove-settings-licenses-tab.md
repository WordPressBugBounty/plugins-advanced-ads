# Remove Settings Licenses Tab — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the legacy Settings → Licenses tab and all UI-only activation paths; license management stays on the React License screen only.

**Architecture:** Add `License_Utils::admin_screen_url()` as the single PHP source for the License admin URL. Strip Settings tab registration, Addon_Updater field rendering, AJAX activate/deactivate, admin.js handlers, and dead methods from `Advanced_Ads_Admin_Licenses`. Update stale notice links. Delete orphaned view templates.

**Tech Stack:** WordPress, PHP 7.4+, PHPUnit 9, legacy admin.js (jQuery)

**Spec:** [../specs/2026-07-01-remove-settings-licenses-tab-design.md](../specs/2026-07-01-remove-settings-licenses-tab-design.md)

**Repo:** `advanced-ads` (client only)

---

## File map

| File                                            | Action | Responsibility                                         |
| ----------------------------------------------- | ------ | ------------------------------------------------------ |
| `includes/license/utils.php`                    | Modify | Add `admin_screen_url()`                               |
| `includes/admin/class-license-admin-post.php`   | Modify | Delegate URL building to `License_Utils`               |
| `admin/includes/notices.php`                    | Modify | License invalid notice link                            |
| `admin/includes/ad-health-notices.php`          | Modify | License invalid notice link                            |
| `includes/admin/class-addon-updater.php`        | Modify | Remove settings fields; update plugin-list link        |
| `includes/admin/class-settings.php`             | Modify | Remove Licenses tab + sections                         |
| `views/admin/screens/settings.php`              | Modify | Remove licenses save-button special case               |
| `includes/admin/class-ajax.php`                 | Modify | Remove license AJAX handlers                           |
| `admin/assets/js/admin.js`                      | Modify | Remove license settings handlers (~183–343)            |
| `admin/includes/class-licenses.php`             | Modify | Remove activate/deactivate/check/firewall/shortcircuit |
| `admin/views/setting-license.php`               | Delete | Legacy per-addon field template                        |
| `views/admin/settings/license/section-help.php` | Delete | Legacy help blurb                                      |
| `views/admin/settings/license/addon-box.php`    | Delete | Orphaned                                               |
| `tests/Unit/License/LicenseTest.php`            | Modify | Test for `admin_screen_url()`                          |

---

## Task 1: `License_Utils::admin_screen_url()` (TDD)

**Files:**

-   Modify: `includes/license/utils.php`
-   Modify: `includes/admin/class-license-admin-post.php`
-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Write failing test**

Add to `LicenseTest.php` (near other `License_Utils` tests):

```php
public function test_admin_screen_url_returns_license_app_path(): void {
	$url = License_Utils::admin_screen_url();

	$this->assertStringContainsString( 'page=advanced-ads-app', $url );
	$this->assertStringContainsString( 'path=%2Flicense', $url );
}

public function test_admin_screen_url_appends_query_args(): void {
	$url = License_Utils::admin_screen_url( [ 'advads_exchange_error' => 'token_expired' ] );

	$this->assertStringContainsString( 'advads_exchange_error=token_expired', $url );
	$this->assertStringContainsString( 'path=%2Flicense', $url );
}
```

-   [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php --filter admin_screen_url`
Expected: FAIL — `admin_screen_url` not defined

-   [ ] **Step 3: Implement helper**

Add to `includes/license/utils.php` inside `License_Utils` (after constants block):

```php
/**
 * License admin screen URL with optional query args.
 *
 * @param array<string, int|string> $query_args Query arguments.
 * @return string
 */
public static function admin_screen_url( array $query_args = [] ): string {
	$base = add_query_arg(
		[
			'page' => 'advanced-ads-app',
			'path' => '/license',
		],
		admin_url( 'admin.php' )
	);

	return $query_args ? add_query_arg( $query_args, $base ) : $base;
}
```

-   [ ] **Step 4: Refactor `License_Admin_Post`**

In `includes/admin/class-license-admin-post.php`:

1. Add `use AdvancedAds\License\License_Utils;`
2. Replace `license_admin_url()` body with:

```php
private function license_admin_url( array $query_args = [] ): string {
	return License_Utils::admin_screen_url( $query_args );
}
```

-   [ ] **Step 5: Run tests**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php --filter admin_screen_url`
Expected: PASS

Run: `vendor/bin/phpunit tests/Unit/Admin/License_Admin_Post_Test.php`
Expected: PASS (no regression)

-   [ ] **Step 6: Commit**

```bash
git add includes/license/utils.php includes/admin/class-license-admin-post.php tests/Unit/License/LicenseTest.php
git commit -m "feat(license): add shared admin_screen_url helper"
```

---

## Task 2: Update notice and plugin-list links

**Files:**

-   Modify: `admin/includes/notices.php`
-   Modify: `admin/includes/ad-health-notices.php`
-   Modify: `includes/admin/class-addon-updater.php`

-   [ ] **Step 1: Update `notices.php`**

Add after existing `use` line:

```php
use AdvancedAds\License\License_Utils;
```

Replace the `license_invalid` text URL (line ~68):

```php
. sprintf( __( 'Please add valid license keys <a href="%s">here</a>.', 'advanced-ads' ), License_Utils::admin_screen_url() ),
```

-   [ ] **Step 2: Update `ad-health-notices.php`**

Replace URL in `license_invalid` entry (line ~98):

```php
\AdvancedAds\License\License_Utils::admin_screen_url()
```

-   [ ] **Step 3: Update `class-addon-updater.php`**

Add `use AdvancedAds\License\License_Utils;` if not present.

In `add_plugin_list_license_notice()`, replace:

```php
admin_url( 'admin.php?page=advanced-ads-settings#top#licenses' )
```

with:

```php
License_Utils::admin_screen_url()
```

-   [ ] **Step 4: Verify no stale links remain**

Run: `rg "settings#top#licenses" --glob "*.php"`
Expected: no matches (or only docs)

-   [ ] **Step 5: Commit**

```bash
git add admin/includes/notices.php admin/includes/ad-health-notices.php includes/admin/class-addon-updater.php
git commit -m "fix(license): point admin notices to License screen"
```

---

## Task 3: Remove Settings Licenses tab

**Files:**

-   Modify: `includes/admin/class-settings.php`
-   Modify: `views/admin/screens/settings.php`

-   [ ] **Step 1: Remove tab and settings registration from `class-settings.php`**

Delete:

-   Constant `ADVADS_SETTINGS_LICENSES` (line ~33)
-   Entire `$tabs['licenses'] = [ ... ];` block in `add_tabs()` (lines ~87–92)
-   `register_setting( self::ADVADS_SETTINGS_LICENSES, self::ADVADS_SETTINGS_LICENSES );` in `settings_init()` (line ~107)
-   `$this->section_licenses();` call in `settings_init()` (line ~114)
-   `$options[] = self::ADVADS_SETTINGS_LICENSES;` in `allow_save_settings()` (line ~152)
-   Method `section_licenses()` (lines ~674–688)
-   Methods `render_settings_licenses_section_callback()` and `render_settings_licenses_pitch_section_callback()` (lines ~692–702)

-   [ ] **Step 2: Simplify save button in `views/admin/screens/settings.php`**

Replace:

```php
if ( isset( $_setting_tab['group'] ) && 'advanced-ads-licenses' !== $_setting_tab['group'] ) {
    submit_button( __( 'Save settings on this page', 'advanced-ads' ) );
}
```

with:

```php
if ( isset( $_setting_tab['group'] ) ) {
    submit_button( __( 'Save settings on this page', 'advanced-ads' ) );
}
```

-   [ ] **Step 3: Smoke-check Settings page**

Manual: open `admin.php?page=advanced-ads-settings` — Licenses tab must be absent; other tabs still save.

-   [ ] **Step 4: Commit**

```bash
git add includes/admin/class-settings.php views/admin/screens/settings.php
git commit -m "refactor(settings): remove legacy Licenses tab"
```

---

## Task 4: Remove Addon_Updater settings license fields

**Files:**

-   Modify: `includes/admin/class-addon-updater.php`

-   [ ] **Step 1: Remove settings hook from `hooks()`**

Delete line:

```php
add_action( 'advanced-ads-settings-init', [ $this, 'add_license_fields' ], 99 );
```

-   [ ] **Step 2: Delete methods**

Remove entire `add_license_fields()` and `render_license_field()` methods (lines ~142–177).

-   [ ] **Step 3: Verify `hooks()` still registers updater**

Confirm these remain in `hooks()`:

-   `License::register_local_development_shop_http_filters()`
-   `plugin_licenses_warning` on `load-plugins.php`
-   `add_on_updater` on `admin_init`

-   [ ] **Step 4: Commit**

```bash
git add includes/admin/class-addon-updater.php
git commit -m "refactor(license): drop settings-page license fields from Addon_Updater"
```

---

## Task 5: Remove license AJAX handlers

**Files:**

-   Modify: `includes/admin/class-ajax.php`

-   [ ] **Step 1: Remove action registrations from `hooks()`**

Delete:

```php
add_action( 'wp_ajax_advads-activate-license', [ $this, 'activate_license' ] );
add_action( 'wp_ajax_advads-deactivate-license', [ $this, 'deactivate_license' ] );
```

-   [ ] **Step 2: Remove methods**

Delete `activate_license()` and `deactivate_license()` methods (lines ~608–663).

-   [ ] **Step 3: Remove unused import**

Delete `use Advanced_Ads_Admin_Licenses;` if no other references remain in the file.

-   [ ] **Step 4: Verify no AJAX references remain**

Run: `rg "advads-activate-license|advads-deactivate-license" --glob "*.{php,js}"`
Expected: only `admin/assets/js/admin.js` (removed in Task 6)

-   [ ] **Step 5: Commit**

```bash
git add includes/admin/class-ajax.php
git commit -m "refactor(license): remove legacy license AJAX endpoints"
```

---

## Task 6: Remove admin.js license handlers

**Files:**

-   Modify: `admin/assets/js/admin.js`

-   [ ] **Step 1: Delete license settings block**

Remove lines ~179–343 inclusive:

-   The `/** SETTINGS PAGE */` comment block for licenses
-   `.advads-license-key` blur handler
-   `.advads-license-activate` click handler
-   `.advads-license-deactivate` click handler
-   `advads_disable_license_buttons()` function

Keep the settings tab hash navigation block starting at `/** There are two formats of URL supported */` (line ~345).

-   [ ] **Step 2: Rebuild admin assets if required**

If the project bundles `admin/assets/js/admin.js` into `assets/dist/`, run the project's JS build (e.g. `npm run build` or equivalent) so dist matches source.

-   [ ] **Step 3: Verify no stale JS references**

Run: `rg "advads-license-activate|advads-license-key|advads_disable_license" --glob "*.{js,jsx,php}"`
Expected: no matches outside deleted view files

-   [ ] **Step 4: Commit**

```bash
git add admin/assets/js/admin.js
git commit -m "refactor(license): remove settings tab license JS handlers"
```

---

## Task 7: Trim `Advanced_Ads_Admin_Licenses`

**Files:**

-   Modify: `admin/includes/class-licenses.php`

-   [ ] **Step 1: Delete UI-only methods**

Remove these methods entirely (lines ~52–349 and ~639–676):

-   `activate_license()`
-   `blocked_by_firewall()`
-   `check_license()`
-   `deactivate_license()`
-   `shortcuit_deactivation()`
-   `get_filtered_licenses()` (only used by `shortcuit_deactivation`)

-   [ ] **Step 2: Confirm retained API**

These must remain unchanged:

-   `get_instance()`, `wp_plugins_loaded()`, constructor hooks
-   `get_licenses()`, `save_licenses()`, `get_license_status()`, `get_license_expires()`
-   `get_probably_all_access()`, `get_probably_all_access_expiry()`
-   `addon_upgrade_filter()`, `get_installed_add_on_by_key()`, `any_license_valid()`
-   `update_license_after_version_info()`, `clear_license_cache()`

-   [ ] **Step 3: Run unit tests**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php`
Expected: PASS

-   [ ] **Step 4: Commit**

```bash
git add admin/includes/class-licenses.php
git commit -m "refactor(license): remove EDD activate/deactivate from Admin_Licenses"
```

---

## Task 8: Delete orphaned view templates

**Files:**

-   Delete: `admin/views/setting-license.php`
-   Delete: `views/admin/settings/license/section-help.php`
-   Delete: `views/admin/settings/license/addon-box.php`

-   [ ] **Step 1: Delete files**

```bash
rm admin/views/setting-license.php
rm views/admin/settings/license/section-help.php
rm views/admin/settings/license/addon-box.php
```

-   [ ] **Step 2: Verify no includes remain**

Run: `rg "setting-license|section-help|settings/license/addon-box" --glob "*.php"`
Expected: no matches (`.pot` references are out of scope)

-   [ ] **Step 3: Commit**

```bash
git add -A admin/views/setting-license.php views/admin/settings/license/
git commit -m "chore(license): delete legacy settings license view templates"
```

---

## Task 9: Final verification

-   [ ] **Step 1: Run full license-related unit tests**

```bash
vendor/bin/phpunit tests/Unit/License/LicenseTest.php tests/Unit/Admin/License_Admin_Post_Test.php
```

Expected: all PASS

-   [ ] **Step 2: Grep for dead references**

```bash
rg "advanced-ads-settings-license-page|ADVADS_SETTINGS_LICENSES|advanced_ads_settings_license|advads-licenses-ajax-referrer" --glob "*.{php,js}"
```

Expected: no matches

-   [ ] **Step 3: Manual checklist** (from spec)

-   [ ] Settings page has no Licenses tab
-   [ ] License screen loads at `admin.php?page=advanced-ads-app&path=/license`
-   [ ] Connect / activate / deactivate add-on on License screen works
-   [ ] Plugin list invalid-license warning links to License screen
-   [ ] Admin notice “add valid license keys” links to License screen
-   [ ] Add-on updates still work with valid license key

-   [ ] **Step 4: Update spec plan link (optional)**

In `docs/superpowers/specs/2026-07-01-remove-settings-licenses-tab-design.md`, replace the “To be written” line with a link to this plan.

-   [ ] **Step 5: Commit spec link update (if changed)**

```bash
git add docs/superpowers/specs/2026-07-01-remove-settings-licenses-tab-design.md
git commit -m "docs: link remove-settings-licenses-tab plan"
```

---

## Spec coverage checklist

| Spec requirement                            | Task                                    |
| ------------------------------------------- | --------------------------------------- |
| No redirect for old `#top#licenses` URL     | Task 3 (tab removed; no redirect added) |
| Full dead-path removal                      | Tasks 4–8                               |
| `License_Utils::admin_screen_url()`         | Task 1                                  |
| Trim `Advanced_Ads_Admin_Licenses`          | Task 7                                  |
| Update 3 notice/plugin-list links           | Task 2                                  |
| Delete view templates                       | Task 8                                  |
| Storage / REST unchanged                    | No tasks touch `License` or REST        |
| Out of scope: `.pot`, full class retirement | Not in plan                             |
