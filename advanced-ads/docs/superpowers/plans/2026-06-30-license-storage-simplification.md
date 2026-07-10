# License Storage Simplification — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate legacy license storage to two options — `advanced-ads-app-licenses` (account catalog) and `advanced-ads-licenses` (site activation map `{ license, status }`) — deleting per-addon slug mirrors on success.

**Architecture:** Phase A exchanges unique legacy keys into rich (unchanged). Phase B transforms flat map + slug-status into activation map, deletes mirrors and `aa-activated-addons`, sets `flat_map_retired`. All reads/writes after retirement use rich + activation map; mirrors are no-op.

**Spec:** [../specs/2026-06-30-license-storage-simplification-design.md](../specs/2026-06-30-license-storage-simplification-design.md)

**Tech Stack:** WordPress, PHP 7.4+, PHPUnit 9

**Repo:** `advanced-ads`

---

## File map

| File                                         | Responsibility                                               |
| -------------------------------------------- | ------------------------------------------------------------ |
| `includes/license/utils.php`                 | `normalize_activation_map()`, helpers to read legacy status  |
| `includes/license/class-license.php`         | Activation map CRUD, transform in Phase B, read/write gates  |
| `upgrades/upgrade-2.0.9.php`                 | Unchanged entry: `maybe_complete_legacy_license_migration()` |
| `admin/includes/class-licenses.php`          | Delegate status/expiry to `License` when retired             |
| `includes/admin/class-addon-updater.php`     | Rich expiry when retired                                     |
| `classes/checks.php`                         | Rich + activation when retired                               |
| `tests/Unit/License/LicenseTest.php`         | Transform, reads, no mirror writes                           |
| `tests/Unit/Upgrades/Upgrade_2_0_9_Test.php` | End-to-end upgrade                                           |

---

## Task 1: Activation map helpers (TDD)

**Files:** `includes/license/utils.php`, `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Failing tests**

```php
public function test_normalize_activation_map_from_legacy_strings(): void {
  $in  = [ 'pro' => 'KEY-A' ];
  $out = License_Utils::normalize_activation_map( $in );
  $this->assertSame( 'KEY-A', $out['pro']['license'] ?? '' );
  $this->assertSame( 'inactive', $out['pro']['status'] ?? '' );
}

public function test_normalize_activation_map_preserves_status(): void {
  $in = [ 'pro' => [ 'license' => 'KEY', 'status' => 'active' ] ];
  $this->assertSame( 'active', License_Utils::normalize_activation_map( $in )['pro']['status'] );
}

public function test_legacy_status_to_activation_active_only_for_valid(): void {
  $this->assertSame( 'active', License_Utils::legacy_mirror_status_to_activation( 'valid' ) );
  $this->assertSame( 'inactive', License_Utils::legacy_mirror_status_to_activation( 'expired' ) );
  $this->assertSame( 'inactive', License_Utils::legacy_mirror_status_to_activation( false ) );
}
```

-   [ ] **Step 2: Run — expect FAIL**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php --filter "normalize_activation_map|legacy_mirror_status"`

-   [ ] **Step 3: Implement** in `utils.php`:

    -   `normalize_activation_map( array $raw ): array` — output `[ addon_id => [ 'license' => string, 'status' => 'active'|'inactive' ] ]`
    -   Accept legacy string values (default `inactive`) and new shape
    -   `legacy_mirror_status_to_activation( $status ): string`

-   [ ] **Step 4: Run — expect PASS**

---

## Task 2: Build activation map from legacy (TDD)

**Files:** `includes/license/class-license.php`, `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Failing test**

```php
public function test_build_activation_map_from_legacy(): void {
  update_option( License::OPTION_LEGACY_MAP, [ 'pro' => 'KEY-A', 'tracking' => 'KEY-B' ], false );
  update_option( 'advanced-ads-pro-license-status', 'valid', false );
  update_option( 'advanced-ads-tracking-license-status', 'expired', false );

  $map = License::build_activation_map_from_legacy_storage();

  $this->assertSame( 'KEY-A', $map['pro']['license'] );
  $this->assertSame( 'active', $map['pro']['status'] );
  $this->assertSame( 'KEY-B', $map['tracking']['license'] );
  $this->assertSame( 'inactive', $map['tracking']['status'] );
}
```

-   [ ] **Step 2: Run — expect FAIL**

-   [ ] **Step 3: Implement** `License::build_activation_map_from_legacy_storage()`:

    -   Normalize legacy flat map keys
    -   For each addon, read `{options_slug}-license-status` via `License_Utils::options_slug_for_addon_id()`
    -   Map `valid` → `active`, else `inactive`

-   [ ] **Step 4: Run — expect PASS**

---

## Task 3: Delete legacy mirror options (TDD)

**Files:** `includes/license/class-license.php`, `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Failing test**

```php
public function test_delete_legacy_addon_mirror_options(): void {
  update_option( 'advanced-ads-pro-license-status', 'valid', false );
  update_option( 'advanced-ads-pro-license-expires', 'lifetime', false );
  update_option( 'advanced-ads-tracking-license-status', 'valid', false );

  License::delete_legacy_addon_mirror_options();

  $this->assertFalse( get_option( 'advanced-ads-pro-license-status', false ) );
  $this->assertFalse( get_option( 'advanced-ads-pro-license-expires', false ) );
  $this->assertFalse( get_option( 'advanced-ads-tracking-license-status', false ) );
}
```

-   [ ] **Step 2–4:** Implement static method looping known add-on slugs from `Data::get_addons()` plus fixed list; `delete_option` both suffixes.

---

## Task 4: Phase B transform (replace delete flat map)

**Files:** `includes/license/class-license.php`, `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Update** `test_maybe_retire_legacy_flat_map_does_not_write_rich`:

    -   Assert activation map shape after retirement
    -   Assert flat map option **still exists** (new shape)
    -   Assert mirrors deleted
    -   Assert `aa-activated-addons` deleted

```php
$activation = License_Utils::normalize_activation_map( get_option( License::OPTION_LEGACY_MAP, [] ) );
$this->assertSame( 'KEY-A', $activation['pro']['license'] );
$this->assertSame( 'active', $activation['pro']['status'] );
$this->assertFalse( get_option( 'advanced-ads-pro-license-status', false ) );
$this->assertFalse( get_option( License::OPTION_AA_ACTIVATED_ADDONS, false ) );
```

-   [ ] **Step 2: Change** `maybe_retire_legacy_flat_map()`:

    -   Remove `delete_option( OPTION_LEGACY_MAP )`
    -   Add `$activation = self::build_activation_map_from_legacy_storage()` (or from `$map` + mirror reads)
    -   `update_option( OPTION_LEGACY_MAP, $activation, false )`
    -   `self::delete_legacy_addon_mirror_options()`
    -   `delete_option( OPTION_AA_ACTIVATED_ADDONS )`
    -   Keep safety check (derived `.license` per addon matches legacy keys)

-   [ ] **Step 3: Run retirement tests + upgrade tests**

---

## Task 5: Read paths when retired (TDD)

**Files:** `includes/license/class-license.php`, `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Tests**

```php
public function test_get_addon_key_map_from_activation_map_when_retired(): void {
  update_option( License::OPTION_FLAT_MAP_RETIRED, '1', false );
  update_option( License::OPTION_LEGACY_MAP, [
    'pro' => [ 'license' => 'KEY-A', 'status' => 'active' ],
  ], false );
  $this->assertSame( 'KEY-A', License::get_addon_key_map()['pro'] );
}

public function test_get_aa_activated_from_activation_map_when_retired(): void {
  update_option( License::OPTION_FLAT_MAP_RETIRED, '1', false );
  update_option( License::OPTION_LEGACY_MAP, [
    'pro'      => [ 'license' => 'AA', 'status' => 'active' ],
    'tracking' => [ 'license' => 'AA', 'status' => 'inactive' ],
  ], false );
  $this->assertSame( [ 'pro' ], License::get_aa_activated_addon_ids() );
}
```

-   [ ] **Step 2: Implement**

    -   `get_addon_key_map()` — when retired, read `.license` from activation map
    -   `get_aa_activated_addon_ids()` — when retired, filter `status === 'active'` (AA rows only if rich says AA — or all active entries)
    -   `get_license_details()` — rich row for expiry; activation map for site status
    -   `addon_license_valid_by_options()` → rich entitled + activation `active` when retired

---

## Task 6: Write paths when retired

**Files:** `includes/license/class-license.php`, `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Test** `update_license_details` when retired updates activation map, not mirrors

-   [ ] **Step 2: Implement**

    -   `update_license_details()` — if retired: merge into activation map; return early before mirror writes
    -   `clear_addon_license_mirror()` — no-op when retired
    -   `sync_addon_options_from_rich()` — skip mirror foreach when retired; update activation map assignments instead
    -   `persist_addon_key_map()` — when retired, update `.license` fields in activation map

---

## Task 7: Delegate legacy admin reads

**Files:** `admin/includes/class-licenses.php`, `includes/admin/class-addon-updater.php`, `classes/checks.php`

-   [ ] **Step 1:** `get_license_status()` / `get_license_expires()` call `License` helpers when `is_flat_map_retired()`

-   [ ] **Step 2:** `Addon_Updater` expiry sweep uses rich `expiryDate` when retired (remove mirror delete logic)

-   [ ] **Step 3:** Manual smoke: plugins.php updater still registers with key from activation map

---

## Task 8: Upgrade + integration tests

**Files:** `tests/Unit/Upgrades/Upgrade_2_0_9_Test.php`

-   [ ] Update `test_upgrade_exchanges_legacy_key_when_rich_empty`:

    -   After upgrade: activation map exists, mirrors gone, `flat_map_retired`

-   [ ] Run full suite:

```bash
vendor/bin/phpunit tests/Unit/License/LicenseTest.php
vendor/bin/phpunit tests/Unit/Upgrades/Upgrade_2_0_9_Test.php
```

---

## Task 9: Deprecate old spec references

**Files:** `docs/superpowers/specs/2026-06-30-license-flat-map-migration-design.md`

-   [ ] Add banner at top: **Superseded by** storage simplification spec
-   [ ] Mark old plan `2026-06-30-license-flat-map-migration.md` as superseded

---

## Verification checklist (local site)

-   [ ] Seed legacy serialized flat map + slug-status options
-   [ ] Set `advanced_ads_db_version` to `2.0.8`, reactivate plugin
-   [ ] Confirm `advanced-ads-app-licenses` populated
-   [ ] Confirm `advanced-ads-licenses` is activation map (not deleted)
-   [ ] Confirm slug-status/expiry options deleted
-   [ ] Licenses UI and add-on updates work
