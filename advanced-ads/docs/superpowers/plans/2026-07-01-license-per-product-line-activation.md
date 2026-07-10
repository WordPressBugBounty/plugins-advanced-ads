# Per-product-line license activation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** One site-active license key per product line; shop-first switch when activating a sibling key; site-only “Active” badge in the License UI.

**Architecture:** Extend `License::activate_on_shop_then_local()` with sibling shop deactivation; add `deactivate_on_shop_then_local()`; generalize `apply_manual_license_activation_on_site()` and `align_mutually_exclusive_site_slots()` using `is_same_product_line_row()`; fix display helpers in `utils.js`.

**Tech Stack:** WordPress, PHP 7.4+, PHPUnit 9, Playwright (`npm run test:playwright`), React License screen

**Spec:** [../specs/2026-07-01-license-per-product-line-activation-design.md](../specs/2026-07-01-license-per-product-line-activation-design.md)

**Repo:** `advanced-ads` (client only — no shop changes)

---

## File map

| File                                                               | Action | Responsibility                                                         |
| ------------------------------------------------------------------ | ------ | ---------------------------------------------------------------------- |
| `includes/license/class-license.php`                               | Modify | Sibling helpers, shop-first switch, align slots, save/deactivate paths |
| `src/admin/screen-licenses/utils.js`                               | Modify | Site-only display status                                               |
| `tests/Unit/License/LicenseTest.php`                               | Modify | PHP activation/exclusivity tests                                       |
| `tests/Acceptance/Admin/Licenses/license-per-product-line.spec.ts` | Create | Playwright display status tests                                        |

---

## Task 1: `find_same_line_site_active_siblings()` (TDD)

**Files:**

-   Modify: `includes/license/class-license.php`
-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Write failing tests**

Add to `LicenseTest.php`:

```php
public function test_find_same_line_site_active_siblings_returns_other_pro_on_site(): void {
	$host = License::get_site_hostname();
	$key_a = 'pro-key-a';
	$key_b = 'pro-key-b';
	$rich = [
		[
			'name'           => 'Advanced Ads Pro',
			'status'         => 'active',
			'licenseKey'     => $key_a,
			'sitesActivated' => [ [ 'domain' => $host ] ],
		],
		[
			'name'           => 'Advanced Ads Pro',
			'status'         => 'active',
			'licenseKey'     => $key_b,
			'sitesActivated' => [],
		],
	];

	$siblings = License::find_same_line_site_active_siblings( $rich, $key_b );

	$this->assertCount( 1, $siblings );
	$this->assertSame( $key_a, $siblings[0]['licenseKey'] ?? '' );
}

public function test_find_same_line_site_active_siblings_excludes_different_product_line(): void {
	$host = License::get_site_hostname();
	$rich = [
		[
			'name'           => 'Advanced Ads Pro',
			'status'         => 'active',
			'licenseKey'     => 'pro-key',
			'sitesActivated' => [ [ 'domain' => $host ] ],
		],
		[
			'name'           => 'Tracking',
			'status'         => 'active',
			'licenseKey'     => 'trk-key',
			'sitesActivated' => [ [ 'domain' => $host ] ],
		],
	];

	$siblings = License::find_same_line_site_active_siblings( $rich, 'trk-key' );

	$this->assertCount( 0, $siblings );
}
```

-   [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php --filter find_same_line_site_active_siblings`
Expected: FAIL — method not defined

-   [ ] **Step 3: Implement**

Add public static method near `apply_manual_license_activation_on_site()`:

```php
/**
 * Rich rows of the same product line that already list this site (excluding target key).
 *
 * @param array<int, array<string, mixed>> $rich        Rich license list.
 * @param string                           $license_key Target license key being activated.
 * @return array<int, array<string, mixed>>
 */
public static function find_same_line_site_active_siblings( array $rich, string $license_key ): array {
	$hostname    = self::get_site_hostname();
	$license_key = trim( $license_key );
	$target_row  = License_Utils::get_rich_license_row_by_key( $rich, $license_key );

	if ( '' === $hostname || ! is_array( $target_row ) ) {
		return [];
	}

	$out = [];
	foreach ( self::normalize_list( $rich ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$key = (string) ( $row['licenseKey'] ?? '' );
		if ( '' === $key || $key === $license_key ) {
			continue;
		}
		if ( ! self::is_same_product_line_row( $target_row, $row ) ) {
			continue;
		}
		if ( ! self::is_site_activated_on_license( $row, $hostname ) ) {
			continue;
		}
		$out[] = $row;
	}

	return $out;
}
```

-   [ ] **Step 4: Run tests**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php --filter find_same_line_site_active_siblings`
Expected: PASS

-   [ ] **Step 5: Commit**

```bash
git add includes/license/class-license.php tests/Unit/License/LicenseTest.php
git commit -m "feat(license): add same-line site-active sibling lookup"
```

---

## Task 2: Shop deactivate siblings before activate (TDD)

**Files:**

-   Modify: `includes/license/class-license.php`
-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Write failing test — switch Pro A → Pro B**

```php
public function test_activate_on_shop_deactivates_same_line_sibling_before_activate(): void {
	$host   = License::get_site_hostname();
	$key_a  = 'pro-key-a';
	$key_b  = 'pro-key-b';
	$rich   = [
		[
			'name'           => 'Advanced Ads Pro',
			'status'         => 'active',
			'licenseKey'     => $key_a,
			'sitesActivated' => [ [ 'domain' => $host ] ],
			'availableSites' => 1,
		],
		[
			'name'           => 'Advanced Ads Pro',
			'status'         => 'active',
			'licenseKey'     => $key_b,
			'sitesActivated' => [],
			'availableSites' => 1,
		],
	];
	$calls = [];

	add_filter(
		'pre_http_request',
		static function ( $pre, $args, $url ) use ( $key_a, $key_b, $host, &$calls ) {
			unset( $pre );
			$body = json_decode( $args['body'] ?? '', true );
			if ( false !== strpos( $url, '/license/deactivate' ) && ( $body['license'] ?? '' ) === $key_a ) {
				$calls[] = 'deactivate_a';
				return [
					'headers'  => [],
					'body'     => wp_json_encode( [ [ 'licenseKey' => $key_a, 'sitesActivated' => [] ] ] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
				];
			}
			if ( false !== strpos( $url, '/license/activate' ) && ( $body['license'] ?? '' ) === $key_b ) {
				$calls[] = 'activate_b';
				return [
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							[
								'name'           => 'Advanced Ads Pro',
								'licenseKey'     => $key_b,
								'sitesActivated' => [ [ 'domain' => $host ] ],
							],
						]
					),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
				];
			}
			return false;
		},
		10,
		3
	);

	$result = License::activate_on_shop_then_local( $key_b, $rich );
	remove_all_filters( 'pre_http_request' );

	$this->assertIsArray( $result );
	$this->assertSame( [ 'deactivate_a', 'activate_b' ], $calls );
	$this->assertFalse( License::is_site_activated_on_license(
		License_Utils::get_rich_license_row_by_key( $result, $key_a ),
		$host
	) );
	$this->assertTrue( License::is_site_activated_on_license(
		License_Utils::get_rich_license_row_by_key( $result, $key_b ),
		$host
	) );
}
```

-   [ ] **Step 2: Run test — expect FAIL**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php --filter activate_on_shop_deactivates_same_line_sibling`
Expected: FAIL — deactivate not called or A still on site

-   [ ] **Step 3: Implement `deactivate_same_line_siblings_on_shop()` and wire into `activate_on_shop_then_local()`**

```php
private static function deactivate_same_line_siblings_on_shop( array $rich, string $license_key ) {
	$siblings = self::find_same_line_site_active_siblings( $rich, $license_key );
	foreach ( $siblings as $row ) {
		$sibling_key = trim( (string) ( $row['licenseKey'] ?? '' ) );
		if ( '' === $sibling_key ) {
			continue;
		}
		$shop = self::request_shop_deactivate( $sibling_key );
		if ( is_wp_error( $shop ) ) {
			return $shop;
		}
		if ( [] !== $shop ) {
			$rich = self::merge_license_lists( $rich, $shop );
		}
	}
	return $rich;
}
```

At start of `activate_on_shop_then_local()` after validating key:

```php
$rich = self::deactivate_same_line_siblings_on_shop( $rich, $license_key );
if ( is_wp_error( $rich ) ) {
	return $rich;
}
```

-   [ ] **Step 4: Extend `apply_manual_license_activation_on_site()`**

Replace the single-product branch comment _“other singles stay active together”_ with same-line sibling stripping (mirror All Access branch):

```php
} elseif ( self::is_same_product_line_row( $target_row, $row ) && $key !== $license_key ) {
	$rich[ $index ] = self::remove_site_hostname_from_license_row( $row, $hostname );
}
```

(Keep existing All Access branch; consolidate if redundant.)

-   [ ] **Step 5: Run tests**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php --filter "activate_on_shop_deactivates_same_line_sibling|activate_on_shop_then_local"`
Expected: PASS (no regressions on existing shop-first tests)

-   [ ] **Step 6: Commit**

```bash
git add includes/license/class-license.php tests/Unit/License/LicenseTest.php
git commit -m "feat(license): shop-deactivate same-line siblings before activate"
```

---

## Task 3: Sibling deactivate failure blocks activation (TDD)

**Files:**

-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Write failing test**

```php
public function test_activate_on_shop_aborts_when_sibling_deactivate_fails(): void {
	$host  = License::get_site_hostname();
	$key_a = 'pro-key-a';
	$key_b = 'pro-key-b';
	$rich  = [
		[
			'name'           => 'Advanced Ads Pro',
			'licenseKey'     => $key_a,
			'sitesActivated' => [ [ 'domain' => $host ] ],
		],
		[
			'name'           => 'Advanced Ads Pro',
			'licenseKey'     => $key_b,
			'sitesActivated' => [],
		],
	];

	add_filter(
		'pre_http_request',
		static function ( $pre, $args, $url ) use ( $key_a ) {
			unset( $pre, $args );
			if ( false !== strpos( $url, '/license/deactivate' ) ) {
				return [
					'headers'  => [],
					'body'     => wp_json_encode( [ 'message' => 'Shop error' ] ),
					'response' => [ 'code' => 500, 'message' => 'Error' ],
				];
			}
			return false;
		},
		10,
		3
	);

	$result = License::activate_on_shop_then_local( $key_b, $rich );
	remove_all_filters( 'pre_http_request' );

	$this->assertInstanceOf( WP_Error::class, $result );
	$this->assertTrue( License::is_site_activated_on_license( $rich[0], $host ) );
}
```

-   [ ] **Step 2: Run — expect PASS if Task 2 implemented correctly; else FAIL**

-   [ ] **Step 3: Commit** (if only test added)

```bash
git add tests/Unit/License/LicenseTest.php
git commit -m "test(license): sibling shop deactivate failure blocks activation"
```

---

## Task 4: `deactivate_on_shop_then_local()` + `save_licenses()` (TDD)

**Files:**

-   Modify: `includes/license/class-license.php`
-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Write failing test**

```php
public function test_deactivate_on_shop_then_local_removes_only_target_key(): void {
	$host  = License::get_site_hostname();
	$key_a = 'pro-key-a';
	$key_b = 'pro-key-b';
	$rich  = [
		[
			'name'           => 'Advanced Ads Pro',
			'licenseKey'     => $key_a,
			'sitesActivated' => [ [ 'domain' => $host ] ],
		],
		[
			'name'           => 'Advanced Ads Pro',
			'licenseKey'     => $key_b,
			'sitesActivated' => [],
		],
	];

	add_filter(
		'pre_http_request',
		static function ( $pre, $args, $url ) use ( $key_a, $host ) {
			unset( $pre );
			if ( false === strpos( $url, '/license/deactivate' ) ) {
				return false;
			}
			$body = json_decode( $args['body'] ?? '', true );
			if ( ( $body['license'] ?? '' ) !== $key_a ) {
				return false;
			}
			return [
				'headers'  => [],
				'body'     => wp_json_encode( [ [ 'licenseKey' => $key_a, 'sitesActivated' => [] ] ] ),
				'response' => [ 'code' => 200, 'message' => 'OK' ],
			];
		},
		10,
		3
	);

	$result = License::deactivate_on_shop_then_local( $key_a, $rich );
	remove_all_filters( 'pre_http_request' );

	$this->assertIsArray( $result );
	$this->assertFalse( License::is_site_activated_on_license(
		License_Utils::get_rich_license_row_by_key( $result, $key_a ),
		$host
	) );
	// B unchanged — still not on site.
	$this->assertFalse( License::is_site_activated_on_license(
		License_Utils::get_rich_license_row_by_key( $result, $key_b ),
		$host
	) );
}
```

-   [ ] **Step 2: Run — expect FAIL**

-   [ ] **Step 3: Implement**

```php
public static function deactivate_on_shop_then_local( string $license_key, array $rich ) {
	$license_key = trim( $license_key );
	if ( '' === $license_key ) {
		return new WP_Error(
			'advanced_ads_license_deactivate_invalid',
			__( 'Missing license key.', 'advanced-ads' )
		);
	}

	$shop = self::request_shop_deactivate( $license_key );
	if ( is_wp_error( $shop ) ) {
		return $shop;
	}
	if ( [] !== $shop ) {
		$rich = self::merge_license_lists( $rich, $shop );
	}

	return self::apply_manual_license_deactivation_on_site( $rich, $license_key );
}
```

In `save_licenses()` replace direct `apply_manual_license_deactivation_on_site` block with:

```php
$merged = self::deactivate_on_shop_then_local( $deactivating_license_key, $merged );
if ( is_wp_error( $merged ) ) {
	return $merged;
}
```

**Conditional plugin deactivate:** Before `deactivate_addon_on_site( $addon_id )`, check:

```php
$still_active = false;
foreach ( self::normalize_list( $merged ) as $row ) {
	if ( ! is_array( $row ) ) {
		continue;
	}
	if ( ! self::is_same_product_line_row( $row, $deactivated_row ) ) {
		continue;
	}
	if ( self::is_site_activated_on_license( $row, self::get_site_hostname() ) ) {
		$still_active = true;
		break;
	}
}
if ( ! $still_active && null !== $addon_id ) {
	self::deactivate_addon_on_site( $addon_id );
}
```

-   [ ] **Step 4: Run tests**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php --filter deactivate_on_shop_then_local`
Expected: PASS

-   [ ] **Step 5: Commit**

```bash
git add includes/license/class-license.php tests/Unit/License/LicenseTest.php
git commit -m "feat(license): shop-first per-key deactivation"
```

---

## Task 5: Generalize `align_mutually_exclusive_site_slots()` (TDD)

**Files:**

-   Modify: `includes/license/class-license.php`
-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Write failing test — two Pro keys on site**

```php
public function test_align_mutually_exclusive_site_slots_keeps_one_pro_winner(): void {
	$host = License::get_site_hostname();
	$rich = [
		[
			'name'           => 'Advanced Ads Pro',
			'status'         => 'active',
			'licenseKey'     => 'pro-low',
			'sites'          => 1,
			'sitesActivated' => [ [ 'domain' => $host ] ],
		],
		[
			'name'           => 'Advanced Ads Pro / 5 sites',
			'status'         => 'active',
			'licenseKey'     => 'pro-high',
			'sites'          => 5,
			'sitesActivated' => [ [ 'domain' => $host ] ],
		],
	];

	$rich = License::align_mutually_exclusive_site_slots( $rich );

	$on_site = 0;
	foreach ( $rich as $row ) {
		if ( License::is_site_activated_on_license( $row, $host ) ) {
			++$on_site;
		}
	}
	$this->assertSame( 1, $on_site );
	$this->assertTrue( License::is_site_activated_on_license(
		License_Utils::get_rich_license_row_by_key( $rich, 'pro-high' ),
		$host
	) );
}
```

-   [ ] **Step 2: Run — expect FAIL**

-   [ ] **Step 3: Refactor `align_mutually_exclusive_site_slots()`**

After building `$allowed`, group site-active rows by product line (use `is_same_product_line_row` with a line representative). Per group with 2+ site-active keys, keep highest `license_priority_score()` winner; `remove_site_hostname_from_license_row` on losers. Preserve existing All Access logic (should merge into generalized approach).

-   [ ] **Step 4: Run**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php --filter align_mutually_exclusive`
Expected: PASS (including existing AA tests)

-   [ ] **Step 5: Commit**

```bash
git add includes/license/class-license.php tests/Unit/License/LicenseTest.php
git commit -m "feat(license): one site slot per product line in align step"
```

---

## Task 6: Cross-line coexistence regression (TDD)

**Files:**

-   Modify: `tests/Unit/License/LicenseTest.php`

-   [ ] **Step 1: Add test** (may already exist as `test_manual_pro_activation_keeps_tracking_on_site` — verify still passes after Tasks 2–5)

```php
public function test_two_product_lines_can_both_be_site_active(): void {
	$host = License::get_site_hostname();
	$key_pro = 'pro-key';
	$key_trk = 'trk-key';
	$rich = [
		[
			'name'           => 'Advanced Ads Pro',
			'licenseKey'     => $key_pro,
			'sitesActivated' => [ [ 'domain' => $host ] ],
		],
		[
			'name'           => 'Tracking',
			'licenseKey'     => $key_trk,
			'sitesActivated' => [ [ 'domain' => $host ] ],
		],
	];

	$rich = License::align_mutually_exclusive_site_slots( $rich );

	$this->assertTrue( License::is_site_activated_on_license(
		License_Utils::get_rich_license_row_by_key( $rich, $key_pro ),
		$host
	) );
	$this->assertTrue( License::is_site_activated_on_license(
		License_Utils::get_rich_license_row_by_key( $rich, $key_trk ),
		$host
	) );
}
```

-   [ ] **Step 2: Run full `LicenseTest.php`**

Run: `vendor/bin/phpunit tests/Unit/License/LicenseTest.php`
Expected: all PASS

-   [ ] **Step 3: Commit** (if new test only)

---

## Task 7: Site-only display status (Playwright)

**Files:**

-   Modify: `src/admin/screen-licenses/utils.js`
-   Create: `tests/Acceptance/Admin/Licenses/license-per-product-line.spec.ts`

-   [ ] **Step 1: Create Playwright spec** (`license-per-product-line.spec.ts`)

Cover:

-   Entitled shop `active` without site → card shows `inactive`
-   Two Pro rows: only the site-activated key shows `active`

-   [ ] **Step 2: Run — expect FAIL** (before `utils.js` fix)

Run: `npm run test:playwright -- tests/Acceptance/Admin/Licenses/license-per-product-line.spec.ts`

-   [ ] **Step 3: Fix `normalizeShopRowStatus()`**

Remove block:

```javascript
if ( isRichLicenseActive( status ) ) {
	return status;
}
```

For non–All Access singles, derive status from site activation:

```javascript
if ( isCurrentSiteActivatedOnLicense( license, currentHostname ) ) {
	return 'active';
}
if ( isRichLicenseEntitled( status, license?.expiryDate ) ) {
	return status === 'inactive' ? 'inactive' : 'inactive';
}
return status;
```

Adjust `getDisplayStatusWithAppliedMap()` / `isLicenseAppliedOnThisSite()` so a shared `pro` addon plugin does not mark sibling keys active — require `isAddonLicensedByKey( addonId, licenseKey, map )` or site activation for that key.

-   [ ] **Step 4: Run Playwright tests**

Run: `npm run test:playwright -- tests/Acceptance/Admin/Licenses/license-per-product-line.spec.ts`
Expected: PASS

-   [ ] **Step 5: Rebuild assets if required**

Run: `npm run build` (if dist bundles admin JS)

-   [ ] **Step 6: Commit**

```bash
git add src/admin/screen-licenses/utils.js tests/Acceptance/Admin/Licenses/license-per-product-line.spec.ts tests/Acceptance/Admin/Licenses/license-listing.spec.ts
git commit -m "fix(license): site-only active badge per license key"
```

---

## Task 8: Final verification

-   [ ] **Step 1: PHP full suite**

```bash
vendor/bin/phpunit tests/Unit/License/LicenseTest.php
```

-   [ ] **Step 2: Playwright license display tests**

```bash
npm run test:playwright -- tests/Acceptance/Admin/Licenses/license-per-product-line.spec.ts
```

-   [ ] **Step 3: Grep regression**

```bash
rg "other singles stay active together" includes/license/class-license.php
```

Expected: no matches

-   [ ] **Step 4: Manual checklist** (from spec)

-   [ ] Two Pro licenses: activate/deactivate independently per card
-   [ ] Switch Pro 1 → Pro 2: only Pro 2 active
-   [ ] Pro + Tracking both active on site
-   [ ] Two All Access: only one site-active

-   [ ] **Step 5: Update spec plan link**

In `docs/superpowers/specs/2026-07-01-license-per-product-line-activation-design.md`:

```markdown
## Implementation plan

[../plans/2026-07-01-license-per-product-line-activation.md](../plans/2026-07-01-license-per-product-line-activation.md)
```

-   [ ] **Step 6: Commit doc link**

```bash
git add docs/superpowers/specs/2026-07-01-license-per-product-line-activation-design.md
git commit -m "docs: link per-product-line activation plan"
```

---

## Spec coverage checklist

| Spec requirement                           | Task   |
| ------------------------------------------ | ------ |
| Shop-first switch within line              | Task 2 |
| Sibling deactivate failure blocks activate | Task 3 |
| Shop-first per-key deactivate              | Task 4 |
| One winner per product line (align)        | Task 5 |
| Cross-line coexistence                     | Task 6 |
| Site-only “Active” badge                   | Task 7 |
| `save_licenses()` conditional plugin off   | Task 4 |
