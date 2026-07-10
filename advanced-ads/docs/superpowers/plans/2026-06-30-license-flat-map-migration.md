# License Flat Map Migration — Implementation Plan

> **Superseded by [2026-06-30-license-storage-simplification.md](./2026-06-30-license-storage-simplification.md)**

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Retire the legacy `advanced-ads-licenses` flat map. **`advanced-ads-app-licenses`** is the canonical store (one row per plan key). Three plans only: Pro, All Access (1 site), All Access (5 sites) — each plan has **one license key**.

**Architecture:** Phase A exchanges each unique legacy key via shop → writes rich. Phase B bootstraps `aa-activated-addons`, deletes flat map. **No** per-addon `{slug}-license-status` / `{slug}-license-expires` sync during migration.

**Tech Stack:** WordPress, PHP 7.4+, PHPUnit 9

**Spec:** [../specs/2026-06-30-license-flat-map-migration-design.md](../specs/2026-06-30-license-flat-map-migration-design.md)

**Repo:** `advanced-ads` (client only)

**Versioning:** Option A — keep `DB_VERSION = '2.0.9'`, rewrite `upgrade-2.0.9.php` before first release.

---

## File map

| File                                         | Responsibility                                                                                                                 |
| -------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `includes/license/class-license.php`         | Retirement helpers, `get_addon_key_map()`, `persist_addon_key_map()` no-op, optional `maybe_retire_legacy_flat_map_if_ready()` |
| `includes/license/utils.php`                 | `unique_legacy_keys()`, `rich_covers_legacy_keys()`                                                                            |
| `upgrades/upgrade-2.0.9.php`                 | Rewrite: read rich, local cleanup only                                                                                         |
| `includes/rest/class-licenses.php`           | Optional: call `maybe_retire_legacy_flat_map_if_ready()` before reconcile                                                      |
| `includes/admin/class-addon-updater.php`     | Use `get_licenses()` instead of direct `get_option`                                                                            |
| `tests/Unit/License/LicenseTest.php`         | Retirement, coverage helpers, `get_addon_key_map`                                                                              |
| `tests/Unit/Upgrades/Upgrade_2_0_9_Test.php` | Upgrade: no rich writes, skip when rich empty                                                                                  |

**Out of scope:** `class-license-exchange.php`, `maybe_backfill_rich_from_legacy()`, any shop validate loop that writes rich.

---

## Task 1: Constants and coverage helpers (TDD)

**Files:**

-   Modify: `includes/license/class-license.php`
-   Modify: `includes/license/utils.php`
-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Write failing tests**

```php
public function test_is_flat_map_retired_false_by_default(): void {
	delete_option( License::OPTION_FLAT_MAP_RETIRED );
	$this->assertFalse( License::is_flat_map_retired() );
}

public function test_unique_legacy_keys_dedupes_values(): void {
	$keys = License_Utils::unique_legacy_keys(
		[ 'pro' => 'AA', 'gam' => 'AA', 'tracking' => 'TRK' ]
	);
	sort( $keys );
	$this->assertSame( [ 'AA', 'TRK' ], $keys );
}

public function test_rich_covers_legacy_keys_when_all_present(): void {
	$map  = [ 'pro' => 'KEY-A', 'gam' => 'KEY-B' ];
	$rich = [
		[ 'licenseKey' => 'KEY-A', 'name' => 'Advanced Ads Pro' ],
		[ 'licenseKey' => 'KEY-B', 'name' => 'GAM' ],
	];
	$this->assertTrue( License_Utils::rich_covers_legacy_keys( $map, $rich ) );
}

public function test_rich_covers_legacy_keys_false_when_missing(): void {
	$map  = [ 'pro' => 'KEY-A', 'gam' => 'KEY-B' ];
	$rich = [ [ 'licenseKey' => 'KEY-A', 'name' => 'Pro' ] ];
	$this->assertFalse( License_Utils::rich_covers_legacy_keys( $map, $rich ) );
}
```

-   [ ] **Step 2: Run — expect FAIL**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php --filter "flat_map_retired|unique_legacy_keys|rich_covers_legacy"`

-   [ ] **Step 3: Implement**

In `class-license.php`:

```php
public const OPTION_FLAT_MAP_RETIRED = 'advanced_ads_licenses_flat_map_retired';

public static function is_flat_map_retired(): bool {
	return '1' === (string) get_option( self::OPTION_FLAT_MAP_RETIRED, '' );
}
```

In `utils.php`: add `unique_legacy_keys()` and `rich_covers_legacy_keys()` as in spec.

-   [ ] **Step 4: Run — expect PASS**

-   [ ] **Step 5: Commit**

```bash
git add includes/license/class-license.php includes/license/utils.php tests/Unit/License/LicenseTest.php
git commit -m "feat(license): add flat map retirement constants and key coverage helpers"
```

---

## Task 2: Bootstrap AA add-ons and retire flat map (TDD)

**Files:**

-   Modify: `includes/license/class-license.php`
-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Write failing tests**

```php
public function test_bootstrap_aa_activated_addons_from_legacy_map(): void {
	$aa_key = 'ALL-ACCESS-KEY';
	$map    = [
		'pro'      => $aa_key,
		'gam'      => $aa_key,
		'tracking' => 'SINGLE-KEY',
	];
	$rich = [
		[ 'licenseKey' => $aa_key, 'name' => 'All Access (1 site)', 'status' => 'active' ],
		[ 'licenseKey' => 'SINGLE-KEY', 'name' => 'Tracking', 'status' => 'active' ],
	];

	License::bootstrap_aa_activated_addons_from_legacy_map( $map, $rich );

	$ids = License::get_aa_activated_addon_ids();
	sort( $ids );
	$this->assertSame( [ 'gam', 'pro' ], $ids );
}

public function test_maybe_retire_legacy_flat_map_does_not_write_rich(): void {
	$map  = [ 'pro' => 'KEY-A' ];
	$rich = [
		[
			'licenseKey' => 'KEY-A',
			'name'       => 'Advanced Ads Pro',
			'status'     => 'active',
			'expiryDate' => 'lifetime',
		],
	];
	update_option( License::OPTION_LEGACY_MAP, $map, false );
	update_option( License::OPTION_RICH, $rich, false );

	$rich_before = get_option( License::OPTION_RICH );

	License::maybe_retire_legacy_flat_map( $rich, $map );

	$this->assertSame( $rich_before, get_option( License::OPTION_RICH ) );
	$this->assertTrue( License::is_flat_map_retired() );
	$this->assertFalse( License::has_stored_legacy_license_map() );
}

public function test_maybe_retire_skips_when_rich_does_not_cover_map(): void {
	update_option( License::OPTION_LEGACY_MAP, [ 'pro' => 'A', 'gam' => 'B' ], false );
	update_option(
		License::OPTION_RICH,
		[ [ 'licenseKey' => 'A', 'name' => 'Pro', 'status' => 'active' ] ],
		false
	);

	License::maybe_retire_legacy_flat_map(
		License::get_licenses(),
		License_Utils::normalize_legacy_map( get_option( License::OPTION_LEGACY_MAP, [] ) )
	);

	$this->assertTrue( License::has_stored_legacy_license_map() );
	$this->assertFalse( License::is_flat_map_retired() );
}
```

-   [ ] **Step 2: Run — expect FAIL**

-   [ ] **Step 3: Implement**

```php
public static function bootstrap_aa_activated_addons_from_legacy_map( array $map, array $rich ): void {
	foreach ( $rich as $row ) {
		if ( ! is_array( $row ) || ! License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) ) ) {
			continue;
		}
		$aa_key = trim( (string) ( $row['licenseKey'] ?? '' ) );
		if ( '' === $aa_key ) {
			continue;
		}
		foreach ( $map as $addon_id => $key ) {
			if ( $key === $aa_key ) {
				self::add_aa_activated_addon_id( (string) $addon_id );
			}
		}
	}
}

public static function maybe_retire_legacy_flat_map( array $rich, array $map ): void {
	if ( self::is_flat_map_retired() || [] === $map || [] === $rich ) {
		return;
	}

	if ( ! License_Utils::rich_covers_legacy_keys( $map, $rich ) ) {
		return;
	}

	$derived = self::build_persisted_addon_key_map_from_rich( $rich );
	$stored  = License_Utils::normalize_legacy_map( get_option( self::OPTION_LEGACY_MAP, [] ) );

	if ( [] !== $stored && wp_json_encode( $derived ) !== wp_json_encode( $stored ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'advanced-ads: flat map retirement skipped — derived map differs from stored map.' );
		}
		return;
	}

	self::bootstrap_aa_activated_addons_from_legacy_map( $map, $rich );
	delete_option( self::OPTION_LEGACY_MAP );
	update_option( self::OPTION_FLAT_MAP_RETIRED, '1', false );
	delete_option( self::OPTION_MIGRATION_DONE );
}

public static function maybe_retire_legacy_flat_map_if_ready(): void {
	if ( self::is_flat_map_retired() ) {
		return;
	}
	$map = License_Utils::normalize_legacy_map( get_option( self::OPTION_LEGACY_MAP, [] ) );
	if ( [] === $map ) {
		return;
	}
	self::maybe_retire_legacy_flat_map( self::get_licenses(), $map );
}
```

**Verify:** `maybe_retire_legacy_flat_map` must not call `update_option( OPTION_RICH, … )` or write per-addon `-license-status` / `-license-expires`.

-   [ ] **Step 4: Run — expect PASS**

-   [ ] **Step 5: Commit**

```bash
git add includes/license/class-license.php tests/Unit/License/LicenseTest.php
git commit -m "feat(license): retire legacy flat map without writing app-licenses"
```

---

## Task 3: Rewrite `upgrade-2.0.9.php` (TDD)

**Files:**

-   Modify: `upgrades/upgrade-2.0.9.php`
-   Create: `tests/Unit/Upgrades/Upgrade_2_0_9_Test.php`

-   [ ] **Step 1: Write failing tests**

```php
<?php
namespace Tests\PHPUnit\Upgrades;

use AdvancedAds\License\License;
use AdvancedAds\License\License_Utils;

class Upgrade_2_0_9_Test extends \WP_UnitTestCase {

	public function test_upgrade_retires_flat_map_when_rich_covers_legacy(): void {
		$rich = [
			[
				'licenseKey' => 'KEY-A',
				'name'       => 'Advanced Ads Pro',
				'status'     => 'active',
				'expiryDate' => 'lifetime',
			],
		];
		update_option( License::OPTION_LEGACY_MAP, [ 'pro' => 'KEY-A' ], false );
		update_option( License::OPTION_RICH, $rich, false );
		delete_option( License::OPTION_FLAT_MAP_RETIRED );

		$rich_writes = 0;
		add_filter(
			'pre_update_option_' . License::OPTION_RICH,
			static function () use ( &$rich_writes ) {
				++$rich_writes;
				return false;
			}
		);

		require ADVADS_ABSPATH . 'upgrades/upgrade-2.0.9.php';

		$this->assertSame( 0, $rich_writes );
		$this->assertTrue( License::is_flat_map_retired() );
	}

	public function test_upgrade_skips_when_rich_empty(): void {
		update_option( License::OPTION_LEGACY_MAP, [ 'pro' => 'KEY-A' ], false );
		delete_option( License::OPTION_RICH );
		delete_option( License::OPTION_FLAT_MAP_RETIRED );

		require ADVADS_ABSPATH . 'upgrades/upgrade-2.0.9.php';

		$this->assertTrue( License::has_stored_legacy_license_map() );
		$this->assertFalse( License::is_flat_map_retired() );
	}
}
```

-   [ ] **Step 2: Run — expect FAIL**

Run: `vendor/bin/phpunit tests/Unit/Upgrades/Upgrade_2_0_9_Test.php`

-   [ ] **Step 3: Replace `upgrades/upgrade-2.0.9.php`**

```php
<?php
/**
 * Retire legacy advanced-ads-licenses flat map when app-licenses already has data.
 *
 * Does NOT write to advanced-ads-app-licenses.
 *
 * @package AdvancedAds
 * @since   2.0.9
 */

use AdvancedAds\Crons\Licenses as License_Cron;
use AdvancedAds\License\License;
use AdvancedAds\License\License_Utils;

defined( 'ABSPATH' ) || exit;

function advads_upgrade_2_0_9_retire_legacy_flat_map(): void {
	if ( License::is_flat_map_retired() ) {
		return;
	}

	$map = License_Utils::normalize_legacy_map( get_option( License::OPTION_LEGACY_MAP, [] ) );
	if ( [] === $map ) {
		update_option( License::OPTION_FLAT_MAP_RETIRED, '1', false );
		return;
	}

	$rich = License::get_licenses();
	if ( [] === $rich || ! License_Utils::rich_covers_legacy_keys( $map, $rich ) ) {
		return;
	}

	License::maybe_retire_legacy_flat_map( $rich, $map );

	if ( License::is_flat_map_retired() ) {
		License_Cron::schedule_license_expiry( License::get_licenses() );
	}
}

advads_upgrade_2_0_9_retire_legacy_flat_map();
```

Remove all `License_Exchange` usage.

-   [ ] **Step 4: Run — expect PASS**

-   [ ] **Step 5: Commit**

```bash
git add upgrades/upgrade-2.0.9.php tests/Unit/Upgrades/Upgrade_2_0_9_Test.php
git commit -m "fix(upgrade): 2.0.9 retires flat map reading app-licenses only"
```

---

## Task 4: Optional runtime retirement on license GET

**Files:**

-   Modify: `includes/rest/class-licenses.php`

-   [ ] **Step 1: Add before passive reconcile**

```php
public function get_licenses(): array {
	License::maybe_retire_legacy_flat_map_if_ready();

	$rich = License::get_licenses();
	$rich = License::reconcile_persisted_licenses( $rich, false, false );
	// ...
}
```

**Note:** `reconcile_persisted_licenses` with `mutate_activation_state = false` only syncs mirrors — confirm it does not write `OPTION_RICH`. If it does, call retirement after reconcile or guard reconcile.

-   [ ] **Step 2: Run unit tests**

Run: `vendor/bin/phpunit tests/Unit/License/`

-   [ ] **Step 3: Commit**

```bash
git add includes/rest/class-licenses.php
git commit -m "feat(license): try flat map retirement on license REST GET"
```

---

## Task 5: `get_addon_key_map()` derives from rich (TDD)

**Files:**

-   Modify: `includes/license/class-license.php`
-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Write failing test**

```php
public function test_get_addon_key_map_derives_from_rich_when_flat_map_retired(): void {
	update_option( License::OPTION_FLAT_MAP_RETIRED, '1', false );
	delete_option( License::OPTION_LEGACY_MAP );
	update_option(
		License::OPTION_RICH,
		[
			[
				'licenseKey'     => 'PRO-KEY',
				'name'           => 'Advanced Ads Pro',
				'status'         => 'active',
				'sitesActivated' => [ License::get_site_hostname() ],
				'availableSites' => 1,
			],
		],
		false
	);

	$this->assertSame( 'PRO-KEY', License::get_addon_key_map()['pro'] ?? '' );
}
```

-   [ ] **Step 2: Replace `get_addon_key_map()`**

```php
public static function get_addon_key_map(): array {
	if ( self::is_flat_map_retired() || ! self::has_stored_legacy_license_map() ) {
		return self::build_persisted_addon_key_map_from_rich( self::get_licenses() );
	}

	$legacy = get_option( self::OPTION_LEGACY_MAP, [] );
	return is_array( $legacy ) ? License_Utils::normalize_legacy_map( $legacy ) : [];
}
```

Remove `is_migration_done()` branch.

-   [ ] **Step 3: Run tests; fix any broken tests using `OPTION_MIGRATION_DONE`**

-   [ ] **Step 4: Commit**

```bash
git commit -m "feat(license): derive addon key map from rich after flat map retired"
```

---

## Task 6: Stop writing flat map; fix `Addon_Updater`

**Files:**

-   Modify: `includes/license/class-license.php`
-   Modify: `includes/admin/class-addon-updater.php`

-   [ ] **Step 1:** `persist_addon_key_map()` — return early when `is_flat_map_retired()`

-   [ ] **Step 2:** `update_license_details()` — skip map write when retired

-   [ ] **Step 3:** `save_licenses()` — wrap `persist_addon_key_map()` calls with `! is_flat_map_retired()`

-   [ ] **Step 4:** `Addon_Updater` — `$licenses = $this->manager->get_licenses();`

-   [ ] **Step 5: Run PHPUnit**

Run: `vendor/bin/phpunit tests/Unit/License/ tests/Unit/Upgrades/`

-   [ ] **Step 6: Commit**

```bash
git commit -m "feat(license): stop persisting flat map; fix addon updater"
```

---

## Task 7: Deprecate `OPTION_MIGRATION_DONE`

-   [ ] Remove production use of `is_migration_done()` except `@deprecated` docblock
-   [ ] Update tests to use `OPTION_FLAT_MAP_RETIRED` instead
-   [ ] Run full PHPUnit
-   [ ] Commit: `chore(license): deprecate licenses_migration flag`

---

## Task 8: graphify update

```bash
graphify update .
```

---

## Verification checklist

-   [ ] No migration code path calls `update_option( 'advanced-ads-app-licenses', … )`
-   [ ] Rich already populated → upgrade retires flat map
-   [ ] Rich empty → flat map kept; existing connect/buy flows populate rich separately
-   [ ] Mirrors synced; EDD updater works after retirement
-   [ ] PHPUnit green

---

## Execution handoff

**Plan saved to `docs/superpowers/plans/2026-06-30-license-flat-map-migration.md`.**

1. **Subagent-Driven** — one task per subagent
2. **Inline** — implement in this session

Which approach?
