# License Activate Flow — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enforce shop-first license activation for legacy and new users across all admin-post exchange paths; legacy users preserve local activations and sync drift to shop; new users auto-activate highest-priority license on connect or checkout license on buy.

**Architecture:** Add `activate_on_shop_then_local()`, `is_legacy_license_store()`, and `sync_local_activations_to_shop()` to `License`. Fix `ensure_site_slots_match_active_assignments()` to never local-only activate. Branch in `save_licenses()` for legacy connect sync vs new user auto-activate. Surface activation errors via admin-post redirect + `LicenseNotices.jsx`.

**Tech Stack:** WordPress, PHP 7.4+, PHPUnit 9, React (notices only), Playwright (verify manual flow)

**Spec:** [../specs/2026-06-29-license-activate-flow-design.md](../specs/2026-06-29-license-activate-flow-design.md)

**Repo:** `advanced-ads` (client only — no shop changes)

---

## File map

| File                                                       | Responsibility                                                                                                |
| ---------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `includes/license/class-license.php`                       | Shop-first helpers, legacy sync, `save_licenses()` branch, fix `ensure_site_slots_match_active_assignments()` |
| `includes/admin/class-license-admin-post.php`              | Handle `WP_Error` from `save_licenses()` → activation error redirect                                          |
| `src/admin/screen-licenses/components/LicenseNotices.jsx`  | `advads_activation_error` / `advads_activation_message` notices                                               |
| `tests/Unit/License/LicenseTest.php`                       | New activation contract tests                                                                                 |
| `tests/Unit/Admin/LicenseAdminPostTest.php`                | Activation error redirect tests (create if missing)                                                           |
| `tests/Acceptance/Admin/Licenses/activate-license.spec.ts` | Verify manual shop-first (no regression)                                                                      |

---

## Task 1: `is_legacy_license_store()` (TDD)

**Files:**

-   Modify: `includes/license/class-license.php`
-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Write failing tests**

Add to `LicenseTest.php`:

```php
public function test_is_legacy_license_store_true_when_rich_licenses_exist(): void {
	update_option( License::OPTION_RICH, [
		[ 'licenseKey' => 'k', 'name' => 'Pro', 'status' => 'active' ],
	], false );
	delete_option( License::OPTION_LEGACY_MAP );

	$this->assertTrue( License::is_legacy_license_store() );
}

public function test_is_legacy_license_store_true_when_legacy_map_exists(): void {
	delete_option( License::OPTION_RICH );
	update_option( License::OPTION_LEGACY_MAP, [ 'pro' => 'legacy-key' ], false );

	$this->assertTrue( License::is_legacy_license_store() );
}

public function test_is_legacy_license_store_false_when_empty(): void {
	delete_option( License::OPTION_RICH );
	delete_option( License::OPTION_LEGACY_MAP );

	$this->assertFalse( License::is_legacy_license_store() );
}
```

-   [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php --filter is_legacy_license_store`
Expected: FAIL — method not defined

-   [ ] **Step 3: Implement**

Add after `has_stored_legacy_license_map()` in `class-license.php`:

```php
/**
 * Whether this site already had license data before an exchange merge.
 *
 * @return bool
 */
public static function is_legacy_license_store(): bool {
	return self::has_stored_licenses() || self::has_stored_legacy_license_map();
}
```

-   [ ] **Step 4: Run tests**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php --filter is_legacy_license_store`
Expected: PASS

-   [ ] **Step 5: Commit**

```bash
git add includes/license/class-license.php tests/Unit/License/LicenseTest.php
git commit -m "feat(license): add is_legacy_license_store helper"
```

---

## Task 2: `activate_on_shop_then_local()` (TDD)

**Files:**

-   Modify: `includes/license/class-license.php`
-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Write failing tests**

```php
public function test_activate_on_shop_then_local_updates_sites_on_shop_success(): void {
	$host = License::get_site_hostname();
	$key  = 'shop-first-key';
	$rich = [
		[
			'name'           => 'Advanced Ads Pro',
			'status'         => 'active',
			'licenseKey'     => $key,
			'sitesActivated' => [],
			'availableSites' => 1,
		],
	];

	add_filter(
		'pre_http_request',
		static function ( $pre, $args, $url ) use ( $key, $host ) {
			unset( $pre );
			if ( false === strpos( $url, '/license/activate' ) ) {
				return false;
			}
			$body = json_decode( $args['body'], true );
			if ( ( $body['license'] ?? '' ) !== $key ) {
				return false;
			}
			return [
				'headers'  => [],
				'body'     => wp_json_encode( [
					[
						'name'           => 'Advanced Ads Pro',
						'status'         => 'active',
						'licenseKey'     => $key,
						'sitesActivated' => [ [ 'domain' => $host ] ],
						'activationCount' => 1,
					],
				] ),
				'response' => [ 'code' => 200, 'message' => 'OK' ],
			];
		},
		10,
		3
	);

	$result = License::activate_on_shop_then_local( $key, $rich );
	remove_all_filters( 'pre_http_request' );

	$this->assertIsArray( $result );
	$this->assertTrue( License::is_site_activated_on_license( $result[0], $host ) );
}

public function test_activate_on_shop_then_local_skips_local_on_shop_failure(): void {
	$host = License::get_site_hostname();
	$key  = 'fail-key';
	$rich = [
		[
			'name'           => 'Advanced Ads Pro',
			'status'         => 'active',
			'licenseKey'     => $key,
			'sitesActivated' => [],
		],
	];

	add_filter(
		'pre_http_request',
		static function () {
			return [
				'headers'  => [],
				'body'     => wp_json_encode( [ 'message' => 'At activation limit.' ] ),
				'response' => [ 'code' => 403, 'message' => 'Forbidden' ],
			];
		},
		10,
		3
	);

	$result = License::activate_on_shop_then_local( $key, $rich );
	remove_all_filters( 'pre_http_request' );

	$this->assertInstanceOf( \WP_Error::class, $result );
	$this->assertFalse( License::is_site_activated_on_license( $rich[0], $host ) );
}
```

-   [ ] **Step 2: Run tests — expect FAIL**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php --filter activate_on_shop_then_local`

-   [ ] **Step 3: Implement**

Add public static method near `request_shop_activate()`:

```php
/**
 * Shop activate then mirror this site on the license row (never local-only).
 *
 * @param string                           $license_key License key.
 * @param array<int, array<string, mixed>> $rich        Rich license list.
 * @return array<int, array<string, mixed>>|WP_Error
 */
public static function activate_on_shop_then_local( string $license_key, array $rich ) {
	$license_key = trim( $license_key );
	if ( '' === $license_key ) {
		return new WP_Error( 'advanced_ads_license_activate_invalid', __( 'Missing license key.', 'advanced-ads' ) );
	}

	$shop = self::request_shop_activate( $license_key );
	if ( is_wp_error( $shop ) ) {
		return $shop;
	}

	if ( [] !== $shop ) {
		$rich = self::merge_license_lists( $rich, $shop );
	}

	return self::apply_manual_license_activation_on_site( $rich, $license_key );
}
```

-   [ ] **Step 4: Run tests — expect PASS**

-   [ ] **Step 5: Commit**

```bash
git add includes/license/class-license.php tests/Unit/License/LicenseTest.php
git commit -m "feat(license): shop-first activate_on_shop_then_local helper"
```

---

## Task 3: Fix `ensure_site_slots_match_active_assignments()` (TDD)

**Files:**

-   Modify: `includes/license/class-license.php`
-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Update existing test + add shop-mock requirement**

The test `test_ensure_site_slots_match_active_assignments_after_exchange_shape` currently passes without shop mock because local-only apply runs. Update it to mock shop activate (copy filter from Task 2). Add test that without shop mock success, site is NOT added locally.

```php
public function test_ensure_site_slots_does_not_activate_locally_without_shop(): void {
	$host = License::get_site_hostname();
	$key  = 'no-shop-mock-key';

	$rich = [
		[
			'name'            => 'Advanced Ads Pro',
			'status'          => 'active',
			'licenseKey'      => $key,
			'activationCount' => 0,
			'availableSites'  => 1,
			'sitesActivated'  => [],
		],
	];

	add_filter(
		'pre_http_request',
		static function () {
			return new \WP_Error( 'http_fail', 'fail' );
		},
		10,
		3
	);

	$rich = License::ensure_site_slots_match_active_assignments( $rich );
	remove_all_filters( 'pre_http_request' );

	$this->assertFalse( License::is_site_activated_on_license( $rich[0], $host ) );
}
```

-   [ ] **Step 2: Run new test — expect FAIL** (local slot still added today)

-   [ ] **Step 3: Replace local-only call in `ensure_site_slots_match_active_assignments()`**

Change the block that calls `apply_manual_license_activation_on_site()` to:

```php
$activated = self::activate_on_shop_then_local( $license_key, $rich );
if ( is_wp_error( $activated ) ) {
	continue;
}
$rich = $activated;
```

-   [ ] **Step 4: Fix `test_ensure_site_slots_match_active_assignments_after_exchange_shape` with shop mock**

-   [ ] **Step 5: Run filter tests — expect PASS**

-   [ ] **Step 6: Commit**

```bash
git add includes/license/class-license.php tests/Unit/License/LicenseTest.php
git commit -m "fix(license): ensure_site_slots requires shop activate success"
```

---

## Task 4: `sync_local_activations_to_shop()` (TDD)

**Files:**

-   Modify: `includes/license/class-license.php`
-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Write failing tests**

```php
public function test_sync_local_activations_calls_shop_for_locally_active_missing_on_shop(): void {
	$host = License::get_site_hostname();
	$key  = 'legacy-local-key';

	update_option( License::OPTION_LEGACY_MAP, [ 'pro' => $key ], false );

	$rich = [
		[
			'name'           => 'Advanced Ads Pro',
			'status'         => 'active',
			'licenseKey'     => $key,
			'sitesActivated' => [ [ 'domain' => $host ] ],
		],
	];

	$shop_called = false;
	add_filter(
		'pre_http_request',
		static function ( $pre, $args, $url ) use ( &$shop_called, $key, $host ) {
			unset( $pre );
			if ( false === strpos( $url, '/license/activate' ) ) {
				return false;
			}
			$body = json_decode( $args['body'], true );
			if ( ( $body['license'] ?? '' ) !== $key ) {
				return false;
			}
			$shop_called = true;
			return [
				'headers'  => [],
				'body'     => wp_json_encode( [
					[
						'licenseKey'     => $key,
						'name'           => 'Advanced Ads Pro',
						'status'         => 'active',
						'sitesActivated' => [ [ 'domain' => $host ] ],
					],
				] ),
				'response' => [ 'code' => 200, 'message' => 'OK' ],
			];
		},
		10,
		3
	);

	$result = License::sync_local_activations_to_shop( $rich );
	remove_all_filters( 'pre_http_request' );

	$this->assertTrue( $shop_called );
	$this->assertIsArray( $result );
}

public function test_sync_local_activations_skips_when_shop_already_has_site(): void {
	$host = License::get_site_hostname();
	$key  = 'already-on-shop';

	$rich = [
		[
			'name'           => 'Advanced Ads Pro',
			'status'         => 'active',
			'licenseKey'     => $key,
			'sitesActivated' => [ [ 'domain' => $host ] ],
		],
	];

	$shop_called = false;
	add_filter(
		'pre_http_request',
		static function () use ( &$shop_called ) {
			$shop_called = true;
			return new \WP_Error( 'should_not_call', 'fail' );
		},
		10,
		3
	);

	$result = License::sync_local_activations_to_shop( $rich );
	remove_all_filters( 'pre_http_request' );

	$this->assertFalse( $shop_called );
	$this->assertIsArray( $result );
}
```

For `test_sync_local_activations_skips_when_shop_already_has_site`, the incoming rich row already has `sitesActivated` with host — sync should treat shop as already having site (use `is_site_activated_on_license` on merged row from assignments). Adjust implementation to skip when row already lists site AND shop exchange row would match — simplest rule: skip keys where `is_site_activated_on_license( $row, $hostname )` is true **and** we only sync keys from local assignments that are missing from exchange incoming. Implementation collects keys from `resolve_addon_license_assignments()` where local assignment exists but `is_site_activated_on_license` on the exchange row is false.

-   [ ] **Step 2: Run — expect FAIL**

-   [ ] **Step 3: Implement `sync_local_activations_to_shop()`**

```php
/**
 * Legacy connect: sync locally-assigned licenses that are not on shop for this site.
 *
 * @param array<int, array<string, mixed>> $rich Rich license list (post-merge).
 * @return array<int, array<string, mixed>>|WP_Error
 */
public static function sync_local_activations_to_shop( array $rich ) {
	$hostname = self::get_site_hostname();
	if ( '' === $hostname ) {
		return $rich;
	}

	$keys_to_sync = [];
	foreach ( self::resolve_addon_license_assignments( $rich ) as $assignment ) {
		$key = (string) ( $assignment['licenseKey'] ?? '' );
		if ( '' === $key ) {
			continue;
		}

		$row = $assignment['row'] ?? null;
		if ( ! is_array( $row ) ) {
			continue;
		}

		if ( self::is_site_activated_on_license( $row, $hostname ) ) {
			continue;
		}

		$keys_to_sync[ $key ] = true;
	}

	foreach ( array_keys( $keys_to_sync ) as $license_key ) {
		$activated = self::activate_on_shop_then_local( $license_key, $rich );
		if ( is_wp_error( $activated ) ) {
			return $activated;
		}
		$rich = $activated;
	}

	return $rich;
}
```

Re-read test 2: locally active with sitesActivated — `is_site_activated_on_license` is true, so skip. Good.

Test 1: legacy map assigns pro, rich has sitesActivated — assignment resolves pro key, row has site locally. Hmm — if sitesActivated has host, `is_site_activated_on_license` is true and we'd skip.

For legacy connect drift scenario: local map says pro=key but exchange returns row with empty sitesActivated (shop missing site). Need sync when **local assignment exists** but **incoming exchange row** lacks shop site.

Adjust logic: compare local assignment keys against incoming row's `sitesActivated` from shop exchange data. If legacy map has key but exchange row `sitesActivated` empty → sync.

Update test 1 rich row to have **empty** `sitesActivated` but legacy map still has pro=key. And ensure `resolve_addon_license_assignments` picks legacy path when no site rows... `has_site_activation_rows` returns false when all empty → legacy assignment from map/score.

Actually with empty sitesActivated, `resolve_addon_license_assignments_legacy` uses scores. Legacy map syncs via `build_persisted_addon_key_map_from_rich` — for test, set legacy map AND rich with empty sites.

Update test 1:

```php
$rich = [
	[
		'name'           => 'Advanced Ads Pro',
		'status'         => 'active',
		'licenseKey'     => $key,
		'sitesActivated' => [],
	],
];
update_option( License::OPTION_LEGACY_MAP, [ 'pro' => $key ], false );
```

And sync logic: keys from legacy map / assignments where `! is_site_activated_on_license( $row, $hostname )` but key is in legacy map or local assignment.

-   [ ] **Step 4: Run tests — expect PASS**

-   [ ] **Step 5: Commit**

```bash
git add includes/license/class-license.php tests/Unit/License/LicenseTest.php
git commit -m "feat(license): sync local activations to shop on legacy connect"
```

---

## Task 5: Branch `save_licenses()` for legacy connect

**Files:**

-   Modify: `includes/license/class-license.php`
-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Write failing test**

```php
public function test_save_licenses_legacy_connect_syncs_without_auto_activating_other_license(): void {
	$host        = License::get_site_hostname();
	$existing_key = 'existing-pro-key';
	$new_key      = 'other-unused-key';

	update_option( License::OPTION_LEGACY_MAP, [ 'pro' => $existing_key ], false );
	update_option( License::OPTION_RICH, [
		[
			'name' => 'Advanced Ads Pro', 'status' => 'active',
			'licenseKey' => $existing_key, 'sitesActivated' => [],
		],
	], false );

	$incoming = [
		[
			'name' => 'Advanced Ads Pro', 'status' => 'active',
			'licenseKey' => $existing_key, 'sitesActivated' => [],
		],
		[
			'name' => 'Tracking', 'status' => 'active',
			'licenseKey' => $new_key, 'sitesActivated' => [],
		],
	];

	$activated_keys = [];
	add_filter(
		'pre_http_request',
		static function ( $pre, $args, $url ) use ( &$activated_keys ) {
			unset( $pre );
			if ( false === strpos( $url, '/license/activate' ) ) {
				return false;
			}
			$body = json_decode( $args['body'], true );
			$activated_keys[] = $body['license'] ?? '';
			return [
				'headers'  => [],
				'body'     => wp_json_encode( [] ),
				'response' => [ 'code' => 200, 'message' => 'OK' ],
			];
		},
		10,
		3
	);

	License::save_licenses( $incoming, false, '' );
	remove_all_filters( 'pre_http_request' );

	$this->assertContains( $existing_key, $activated_keys );
	$this->assertNotContains( $new_key, $activated_keys );
}
```

-   [ ] **Step 2: Run — expect FAIL** (new_key might get auto-activated today)

-   [ ] **Step 3: Add branch in `save_licenses()` after merge**

Capture `$is_legacy = self::is_legacy_license_store()` using `$existing` **before** merge (already available as `$existing`).

Before `$try_shop_activate` block, insert:

```php
$is_legacy = self::is_legacy_license_store();

if (
	$is_legacy
	&& ! $activate_new
	&& '' === trim( $activating_license_key )
	&& ! $has_new_keys
) {
	$synced = self::sync_local_activations_to_shop( $merged );
	if ( is_wp_error( $synced ) ) {
		return $synced;
	}
	$merged = $synced;
	update_option( self::OPTION_RICH, $merged, false );
	$try_shop_activate = false;
}
```

When `$has_new_keys` is true (legacy buy), existing post-checkout / `incoming_new_keys_need_shop_activation` paths still run.

-   [ ] **Step 4: Run test — expect PASS**

-   [ ] **Step 5: Commit**

```bash
git add includes/license/class-license.php tests/Unit/License/LicenseTest.php
git commit -m "feat(license): legacy connect sync branch in save_licenses"
```

---

## Task 6: Admin-post activation error redirect

**Files:**

-   Modify: `includes/admin/class-license-admin-post.php`
-   Create or modify: `tests/Unit/Admin/LicenseAdminPostTest.php`

-   [ ] **Step 1: Write failing test**

```php
public function test_handle_redirects_activation_error_when_save_returns_wp_error(): void {
	// Mock user capability, token exchange success, save_licenses returns WP_Error
	// Assert redirect URL contains advads_activation_error=activate_failed
}
```

Use patterns from `Exchange_Test.php` / existing admin post tests if present.

-   [ ] **Step 2: Run — expect FAIL**

-   [ ] **Step 3: Update `handle()` after `save_licenses()`**

```php
$saved = License::save_licenses(
	$rows,
	'' !== $activating_key,
	$activating_key
);

if ( is_wp_error( $saved ) ) {
	$message = $saved->get_error_message();
	$args    = [
		'advads_activation_error' => 'activate_failed',
	];
	if ( '' !== $message ) {
		$args['advads_activation_message'] = substr( sanitize_text_field( $message ), 0, 200 );
	}
	wp_safe_redirect( $this->license_admin_url( $args ) );
	exit;
}
```

Note: `save_licenses` currently returns array — only returns WP_Error for deactivate errors today. After Task 5, sync can return WP_Error.

-   [ ] **Step 4: Run test — PASS**

-   [ ] **Step 5: Commit**

```bash
git add includes/admin/class-license-admin-post.php tests/Unit/Admin/LicenseAdminPostTest.php
git commit -m "feat(license): admin-post redirect on activation failure"
```

---

## Task 7: `LicenseNotices.jsx` activation errors

**Files:**

-   Modify: `src/admin/screen-licenses/components/LicenseNotices.jsx`

-   [ ] **Step 1: Add handler mirroring `advads_exchange_error`**

Read existing `advads_exchange_error` block and add:

```javascript
const activationError = params.get( 'advads_activation_error' );
const activationMessage = params.get( 'advads_activation_message' );
```

Map codes:

-   `network` → connection error string
-   `activate_failed` → use `activationMessage` or generic "Failed to activate license on the shop."
-   `forbidden` → permission string

Strip params from URL after display (same as other notice params).

-   [ ] **Step 2: Build assets**

Run: `npm run build` (or project-equivalent)

-   [ ] **Step 3: Commit**

```bash
git add src/admin/screen-licenses/components/LicenseNotices.jsx assets/dist/
git commit -m "feat(license): show activation error notices from admin-post"
```

---

## Task 8: Verify manual shop-first (Playwright)

**Files:**

-   Read: `tests/Acceptance/Admin/Licenses/activate-license.spec.ts`

-   [ ] **Step 1: Confirm existing tests cover shop 200 success and 403 failure**

No code changes expected. Run:

```bash
npx playwright test tests/Acceptance/Admin/Licenses/activate-license.spec.ts
```

Expected: PASS

-   [ ] **Step 2: If gap found, add assertion that local REST save is not called on shop 403**

Only if verification fails.

-   [ ] **Step 3: Commit** (only if test changes)

---

## Task 9: Full test suite

-   [ ] **Step 1: Run PHPUnit license tests**

```bash
vendor/bin/phpunit tests/Unit/License/
vendor/bin/phpunit tests/Unit/Admin/LicenseAdminPostTest.php
```

Expected: PASS

-   [ ] **Step 2: Run graphify update** (if available in environment)

```bash
graphify update .
```

---

## Spec coverage self-review

| Spec requirement          | Task                                                         |
| ------------------------- | ------------------------------------------------------------ |
| Shop-first contract       | Task 2, 3                                                    |
| Legacy vs new detection   | Task 1                                                       |
| Legacy connect sync       | Task 4, 5                                                    |
| New user auto-activate    | Task 3, 5 (existing `maybe_activate` path, shop-first fixed) |
| Manual switch verify      | Task 8                                                       |
| Admin-post error redirect | Task 6, 7                                                    |
| No shop changes           | —                                                            |

## Execution handoff

**Plan complete and saved to `docs/superpowers/plans/2026-06-29-license-activate-flow.md`.**

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks
2. **Inline Execution** — implement tasks in this session with checkpoints

Which approach?
