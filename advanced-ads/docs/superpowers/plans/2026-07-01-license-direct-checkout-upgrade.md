# License direct checkout upgrade — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade and renew from the License screen go to shop checkout (not `sso-login`); guests log in on checkout; upgrade modal shows only valid target plans.

**Architecture:** Client builds checkout bridge URLs (`site` + `intent` + ids) matching the existing `add_to_cart` buy flow. Shop `maybe_handle_plugin_checkout_entry()` stops sending guests to `sso-login` and redirects to EDD checkout with pending intent stored for `resume_pending_intent_after_login()`. UI filters pricing modal by `getAllowedUpgradePlanIds()`.

**Tech Stack:** WordPress, PHP 7.4+, PHPUnit 9, Playwright (`npm run test:playwright`), React License screen

**Spec:** [../specs/2026-07-01-license-direct-checkout-upgrade-design.md](../specs/2026-07-01-license-direct-checkout-upgrade-design.md)

**Repos:** `advanced-ads` (client), `advanced-ads-licenses` (shop)

---

## File map

| File                                                               | Repo   | Action | Responsibility                                 |
| ------------------------------------------------------------------ | ------ | ------ | ---------------------------------------------- |
| `includes/checkout/class-checkout.php`                             | shop   | Modify | Guest upgrade/renew → checkout, not sso-login  |
| `tests/Unit/Checkout/CheckoutEntryTest.php`                        | shop   | Modify | Guest redirect expectations                    |
| `src/admin/screen-licenses/utils.js`                               | client | Modify | Checkout bridge URLs, allowed upgrade plan ids |
| `src/admin/screen-licenses/components/PricingTable.jsx`            | client | Modify | Filter plans by allowed ids                    |
| `src/admin/screen-licenses/components/PricingModal.jsx`            | client | Modify | Pass `allowedPlanIds` prop                     |
| `src/admin/screen-licenses/components/LicenseItem.jsx`             | client | Modify | Hide Upgrade menu when no targets              |
| `tests/Acceptance/Admin/Licenses/licensePage.ts`                   | client | Modify | `assertCheckoutBridgeParams` helper            |
| `tests/Acceptance/Admin/Licenses/license-manage.spec.ts`           | client | Modify | Expect checkout bridge, not sso-login          |
| `tests/Acceptance/Admin/Licenses/license-expiry-notices.spec.ts`   | client | Modify | Renew → checkout bridge                        |
| `tests/Acceptance/Admin/Licenses/license-upgrade-checkout.spec.ts` | client | Create | Modal matrix + AA-five hide upgrade            |

---

## Task 1: Shop — guest upgrade redirects to checkout (TDD)

**Files:**

-   Modify: `advanced-ads-licenses/includes/checkout/class-checkout.php`
-   Modify: `advanced-ads-licenses/tests/Unit/Checkout/CheckoutEntryTest.php`

-   [ ] **Step 1: Update failing test**

In `CheckoutEntryTest.php`, replace `test_guest_upgrade_redirects_to_connect` with:

```php
/**
 * Guest upgrade redirects to checkout (not sso-login).
 */
public function test_guest_upgrade_redirects_to_checkout(): void {
	\wp_set_current_user( 0 );
	$site = 'https://client.test/wp-admin/admin.php?page=advanced-ads-app&path=%2Flicense';
	$_GET = [
		'intent'      => 'upgrade',
		'license_id'  => '10',
		'download_id' => '95170',
		'pricing_id'  => '1',
		'site'        => $site,
	];

	$redirect = '';
	\add_filter(
		'wp_redirect',
		static function ( $location ) use ( &$redirect ) {
			$redirect = (string) $location;
			throw new \Exception( 'redirect' );
		}
	);

	try {
		$this->checkout->maybe_handle_plugin_checkout_entry();
		$this->fail( 'Expected redirect' );
	} catch ( \Exception $e ) {
		$this->assertSame( 'redirect', $e->getMessage() );
	}

	$this->assertStringNotContainsString( '/sso-login', $redirect );
	$this->assertStringContainsString( '/checkout', $redirect );

	$pending = $this->edd_session->get( Constants::PENDING_INTENT_SESSION_KEY );
	$this->assertSame( 'upgrade', $pending['intent'] );
	$this->assertSame( 10, $pending['license_id'] );

	$context = $this->edd_session->get( Constants::CHECKOUT_CONTEXT_SESSION_KEY );
	$this->assertSame( 'upgrade', $context['intent'] );
	$this->assertSame( 10, $context['license_id'] );
}
```

-   [ ] **Step 2: Run test — expect FAIL**

Run (shop repo):

```bash
vendor/bin/phpunit tests/Unit/Checkout/CheckoutEntryTest.php --filter test_guest_upgrade_redirects_to_checkout
```

Expected: FAIL — redirect still contains `sso-login`

-   [ ] **Step 3: Implement guest checkout redirect**

In `class-checkout.php` `maybe_handle_plugin_checkout_entry()`, replace the guest block:

```php
// Guest upgrade/renew: login first — bare checkout has no renewal in cart and may redirect to pricing.
if ( ( $is_upgrade || $is_renew ) && ! \is_user_logged_in() ) {
	$this->redirect_guest_to_connect( $context );
}
```

With:

```php
if ( ( $is_upgrade || $is_renew ) && ! \is_user_logged_in() ) {
	if ( \function_exists( 'edd_get_checkout_uri' ) ) {
		\wp_safe_redirect( (string) \edd_get_checkout_uri() );
		exit;
	}
}
```

(`store_return_site`, `store_pending_intent`, and `store_checkout_context` already run above for upgrade/renew.)

-   [ ] **Step 4: Run test — expect PASS**

```bash
vendor/bin/phpunit tests/Unit/Checkout/CheckoutEntryTest.php --filter test_guest_upgrade_redirects_to_checkout
```

-   [ ] **Step 5: Commit (shop)**

```bash
git add includes/checkout/class-checkout.php tests/Unit/Checkout/CheckoutEntryTest.php
git commit -m "fix(checkout): guest upgrade lands on checkout, not sso-login"
```

---

## Task 2: Shop — guest renew redirects to checkout (TDD)

**Files:**

-   Modify: `advanced-ads-licenses/tests/Unit/Checkout/CheckoutEntryTest.php`

-   [ ] **Step 1: Update test**

Replace `test_guest_renew_redirects_to_connect` analogously:

```php
$this->assertStringNotContainsString( '/sso-login', $redirect );
$this->assertStringContainsString( '/checkout', $redirect );
```

Keep pending-intent assertions.

-   [ ] **Step 2: Run — expect PASS** (Task 1 implementation covers renew)

```bash
vendor/bin/phpunit tests/Unit/Checkout/CheckoutEntryTest.php --filter test_guest_renew
```

-   [ ] **Step 3: Commit (shop, if only test rename/assertions changed)**

```bash
git add tests/Unit/Checkout/CheckoutEntryTest.php
git commit -m "test(checkout): guest renew redirects to checkout"
```

---

## Task 3: Client — checkout bridge URL builders (TDD via Playwright)

**Files:**

-   Modify: `advanced-ads/src/admin/screen-licenses/utils.js`
-   Modify: `advanced-ads/tests/Acceptance/Admin/Licenses/licensePage.ts`
-   Modify: `advanced-ads/tests/Acceptance/Admin/Licenses/license-manage.spec.ts`

-   [ ] **Step 1: Add URL assertion helper**

In `licensePage.ts`:

```typescript
export function assertCheckoutBridgeParams(
	url: string,
	expected: {
		intent: 'upgrade' | 'renew';
		licenseId?: string;
		downloadId?: string;
		pricingId?: string | null;
		siteContains?: string;
	}
) {
	const parsed = new URL( url );
	expect( parsed.pathname ).toContain( '/checkout' );
	expect( parsed.searchParams.get( 'intent' ) ).toBe( expected.intent );

	if ( expected.licenseId ) {
		expect( parsed.searchParams.get( 'license_id' ) ).toBe(
			expected.licenseId
		);
	}
	if ( expected.downloadId ) {
		expect( parsed.searchParams.get( 'download_id' ) ).toBe(
			expected.downloadId
		);
	}
	if ( expected.pricingId === null ) {
		expect( parsed.searchParams.get( 'pricing_id' ) ).toBeNull();
	} else if ( expected.pricingId ) {
		expect( parsed.searchParams.get( 'pricing_id' ) ).toBe(
			expected.pricingId
		);
	}
	if ( expected.siteContains ) {
		expect( parsed.searchParams.get( 'site' ) ?? '' ).toContain(
			expected.siteContains
		);
	}
	expect( parsed.pathname ).not.toContain( 'sso-login' );
}
```

-   [ ] **Step 2: Update Playwright upgrade test expectations**

In `license-manage.spec.ts`, change upgrade SSO test to intercept navigation and assert checkout bridge:

```typescript
test( 'Upgrade plan selection opens checkout bridge with upgrade intent', async ( {
	page,
} ) => {
	await withLicenseListing( page, [ proLicenseActive() ] );
	await gotoLicensePage( page );
	await waitForLicensesLoaded( page );

	await openUpgradeModalFromManage( page );

	const navigation = page.waitForURL( /\/checkout/ );
	const allAccess = CHECKOUT_PLANS.find(
		( plan ) => plan.id === 'all-access-single'
	)!;
	await clickPlanCtaInModal( page, allAccess.cta, { waitFor: /\/checkout/ } );
	await navigation;

	assertCheckoutBridgeParams( page.url(), {
		intent: 'upgrade',
		licenseId: '37013',
		downloadId: allAccess.downloadId,
		pricingId: allAccess.pricingId,
		siteContains: 'path',
	} );
} );
```

Remove `assertSsoLoginParams` / `waitFor: /sso-login/` from this test.

-   [ ] **Step 3: Run Playwright — expect FAIL**

```bash
PLAYWRIGHT_HTML_OPEN=never npm run test:playwright -- --project=admin-licenses license-manage.spec.ts
```

-   [ ] **Step 4: Implement checkout bridge builders**

In `utils.js`, add after `buildShopCheckoutUrl`:

```javascript
/**
 * Shop checkout bridge URL for license upgrade (replaces sso-login).
 *
 * @param {Object} params
 * @param {number} params.licenseId  EDD license post ID.
 * @param {number} params.downloadId Target download ID.
 * @param {number} params.pricingId  Target variable price ID.
 * @return {string} Checkout bridge URL.
 */
export function buildShopUpgradeCheckoutUrl( {
	licenseId,
	downloadId,
	pricingId,
} ) {
	const url = new URL( `${ advancedAds.endpoints.shopUrl }/checkout/` );
	url.searchParams.set( 'site', buildLicenseAdminUrl() );
	url.searchParams.set( 'intent', 'upgrade' );
	url.searchParams.set( 'license_id', String( licenseId ) );
	url.searchParams.set( 'download_id', String( downloadId ) );
	if ( Number( pricingId ) > 0 ) {
		url.searchParams.set( 'pricing_id', String( pricingId ) );
	}
	return url.toString();
}

/**
 * Shop checkout bridge URL for license renewal (replaces sso-login).
 *
 * @param {Object} params
 * @param {number} params.licenseId EDD license post ID.
 * @return {string} Checkout bridge URL.
 */
export function buildShopRenewalCheckoutUrl( { licenseId } ) {
	const url = new URL( `${ advancedAds.endpoints.shopUrl }/checkout/` );
	url.searchParams.set( 'site', buildLicenseAdminUrl() );
	url.searchParams.set( 'intent', 'renew' );
	url.searchParams.set( 'license_id', String( licenseId ) );
	return url.toString();
}
```

Update `startShopUpgradeForPlan`:

```javascript
globalThis.location.href = buildShopUpgradeCheckoutUrl( {
	licenseId,
	downloadId: ids.downloadId,
	pricingId: ids.pricingId,
} );
```

Update `startShopRenewalForLicense`:

```javascript
globalThis.location.href = buildShopRenewalCheckoutUrl( { licenseId } );
```

Leave `buildShopUpgradeUrl` / `buildShopRenewalUrl` (SSO) in place only if still referenced elsewhere; otherwise remove or add `@deprecated` JSDoc pointing to checkout bridge builders. Grep before deleting.

-   [ ] **Step 5: Rebuild assets**

```bash
npm run build
```

-   [ ] **Step 6: Run Playwright — expect PASS**

```bash
PLAYWRIGHT_HTML_OPEN=never npm run test:playwright -- --project=admin-licenses license-manage.spec.ts
```

-   [ ] **Step 7: Commit (client)**

```bash
git add src/admin/screen-licenses/utils.js tests/Acceptance/Admin/Licenses/licensePage.ts tests/Acceptance/Admin/Licenses/license-manage.spec.ts
git commit -m "feat(license): direct checkout bridge for upgrade"
```

---

## Task 4: Client — renew uses checkout bridge

**Files:**

-   Modify: `advanced-ads/tests/Acceptance/Admin/Licenses/license-expiry-notices.spec.ts` (or `license-manage.spec.ts`)

-   [ ] **Step 1: Update renew test**

Change renew flow test from `waitForURL( /sso-login/ )` to checkout bridge assertion using `assertCheckoutBridgeParams` with `intent: 'renew'`.

-   [ ] **Step 2: Run Playwright**

```bash
PLAYWRIGHT_HTML_OPEN=never npm run test:playwright -- --project=admin-licenses license-expiry-notices.spec.ts
```

Expected: PASS (implementation from Task 3)

-   [ ] **Step 3: Commit (if only test file changed)**

```bash
git add tests/Acceptance/Admin/Licenses/license-expiry-notices.spec.ts
git commit -m "test(license): renew uses checkout bridge"
```

---

## Task 5: Client — `getAllowedUpgradePlanIds()` + pricing modal filter

**Files:**

-   Modify: `advanced-ads/src/admin/screen-licenses/utils.js`
-   Modify: `advanced-ads/src/admin/screen-licenses/components/PricingTable.jsx`
-   Modify: `advanced-ads/src/admin/screen-licenses/components/PricingModal.jsx`
-   Modify: `advanced-ads/src/admin/screen-licenses/components/LicenseItem.jsx`
-   Create: `advanced-ads/tests/Acceptance/Admin/Licenses/license-upgrade-checkout.spec.ts`

-   [ ] **Step 1: Write Playwright tests (fail before UI filter)**

Create `license-upgrade-checkout.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';

import {
	allAccessFiveLicense,
	allAccessSingleLicense,
	proLicenseActive,
} from './licenseFixtures';
import { withLicenseListing } from './licenseMocks';
import {
	gotoLicensePage,
	openManageMenu,
	pricingModal,
	waitForLicensesLoaded,
} from './licensePage';

test.describe( 'Upgrade modal plan matrix', () => {
	test( 'Pro license shows only All Access targets in upgrade modal', async ( {
		page,
	} ) => {
		await withLicenseListing( page, [ proLicenseActive() ] );
		await gotoLicensePage( page );
		await waitForLicensesLoaded( page );

		await openManageMenu( page );
		await page.getByRole( 'menuitem', { name: 'Upgrade plan' } ).click();

		const modal = pricingModal( page );
		await expect( modal.getByText( 'Single site' ).first() ).toBeVisible();
		await expect( modal.getByText( '5 sites' ) ).toBeVisible();
		await expect(
			modal.getByRole( 'button', { name: 'Upgrade to Pro', exact: true } )
		).toHaveCount( 0 );
	} );

	test( 'All Access 1-site shows only 5-site target', async ( { page } ) => {
		await withLicenseListing( page, [ allAccessSingleLicense() ] );
		await gotoLicensePage( page );
		await waitForLicensesLoaded( page );

		await openManageMenu( page );
		await page.getByRole( 'menuitem', { name: 'Upgrade plan' } ).click();

		const modal = pricingModal( page );
		await expect( modal.getByText( '5 sites' ) ).toBeVisible();
		await expect(
			modal.getByRole( 'button', { name: 'Get All Access', exact: true } )
		).toHaveCount( 0 );
	} );

	test( 'All Access 5-site has no Upgrade plan menu item', async ( {
		page,
	} ) => {
		await withLicenseListing( page, [ allAccessFiveLicense() ] );
		await gotoLicensePage( page );
		await waitForLicensesLoaded( page );

		await openManageMenu( page );
		await expect(
			page.getByRole( 'menuitem', { name: 'Upgrade plan' } )
		).toHaveCount( 0 );
	} );
} );
```

Add fixtures to `licenseFixtures.ts` if missing:

```typescript
export function allAccessSingleLicense(
	adminHost = 'localhost'
): ExchangeLicense {
	return {
		name: 'All Access',
		status: 'active',
		licenseId: 37020,
		licenseKey: 'aa-single-key-000000000000000000000000',
		activationCount: 0,
		availableSites: 1,
		purchaseDate: '15-06-2026',
		expiryDate: daysFromNow( 365 ),
		autoRenew: false,
		paymentStatus: 'complete',
		sitesActivated: [],
	};
}

export function allAccessFiveLicense(
	adminHost = 'localhost'
): ExchangeLicense {
	return {
		...allAccessSingleLicense( adminHost ),
		name: 'All Access / 5 sites',
		licenseId: 37021,
		licenseKey: 'aa-five-key-00000000000000000000000000',
		availableSites: 5,
	};
}
```

-   [ ] **Step 2: Run Playwright — expect FAIL**

```bash
PLAYWRIGHT_HTML_OPEN=never npm run test:playwright -- --project=admin-licenses license-upgrade-checkout.spec.ts
```

-   [ ] **Step 3: Implement `getAllowedUpgradePlanIds`**

In `utils.js`:

```javascript
/**
 * Target plan ids allowed when upgrading from the current plan.
 *
 * @param {'pro'|'all-access-single'|'all-access-five'|null} currentPlanId
 * @return {string[]} Allowed PricingTable plan ids (empty = no upgrade).
 */
export function getAllowedUpgradePlanIds( currentPlanId ) {
	switch ( currentPlanId ) {
		case 'pro':
			return [ 'all-access-single', 'all-access-five' ];
		case 'all-access-single':
			return [ 'all-access-five' ];
		default:
			return [];
	}
}

export function canUpgradeLicensePlan( currentPlanId ) {
	return getAllowedUpgradePlanIds( currentPlanId ).length > 0;
}
```

-   [ ] **Step 4: Filter `PricingTable`**

Add prop `allowedPlanIds` (optional). When set, filter `PLANS`:

```javascript
const visiblePlans =
	allowedPlanIds && allowedPlanIds.length > 0
		? PLANS.filter( ( plan ) => allowedPlanIds.includes( plan.id ) )
		: PLANS;
```

Map `visiblePlans` instead of `PLANS`. Adjust grid classes if only one card (optional: `md:grid-cols-1` when length === 1).

-   [ ] **Step 5: Wire `PricingModal` + `LicenseItem`**

`PricingModal`: accept `allowedPlanIds`, pass to `PricingTable`.

`LicenseItem`:

```javascript
const currentPlanId = resolvePlanIdForLicense( licenseRow );
const allowedUpgradePlanIds = getAllowedUpgradePlanIds( currentPlanId );
const showUpgradePlan = canUpgradeLicensePlan( currentPlanId );

// In DropdownMenu controls:
...( showUpgradePlan
	? [ { title: __( 'Upgrade plan', 'advanced-ads' ), onClick: handlePricingOpen } ]
	: [] ),

// PricingModal:
allowedPlanIds={ allowedUpgradePlanIds }
```

-   [ ] **Step 6: Rebuild + run Playwright**

```bash
npm run build
PLAYWRIGHT_HTML_OPEN=never npm run test:playwright -- --project=admin-licenses license-upgrade-checkout.spec.ts
```

Expected: PASS

-   [ ] **Step 7: Commit (client)**

```bash
git add src/admin/screen-licenses/utils.js src/admin/screen-licenses/components/PricingTable.jsx src/admin/screen-licenses/components/PricingModal.jsx src/admin/screen-licenses/components/LicenseItem.jsx tests/Acceptance/Admin/Licenses/licenseFixtures.ts tests/Acceptance/Admin/Licenses/license-upgrade-checkout.spec.ts
git commit -m "feat(license): filter upgrade modal by plan matrix"
```

---

## Task 6: Final verification

-   [ ] **Step 1: Shop PHPUnit full checkout entry suite**

```bash
vendor/bin/phpunit tests/Unit/Checkout/CheckoutEntryTest.php
```

Expected: all PASS (including `test_wp_login_resumes_pending_upgrade` regression)

-   [ ] **Step 2: Client Playwright license project**

```bash
PLAYWRIGHT_HTML_OPEN=never npm run test:playwright -- --project=admin-licenses
```

-   [ ] **Step 3: Regression grep**

```bash
rg "startShopUpgradeForPlan|buildShopUpgradeUrl" src/admin/screen-licenses
```

Confirm upgrade path uses `buildShopUpgradeCheckoutUrl`, not SSO builder.

-   [ ] **Step 4: Manual checklist**

-   [ ] Pro → Upgrade → lands on shop checkout (login if guest)
-   [ ] AA 1-site → Upgrade → only 5-site option; checkout works
-   [ ] AA 5-site → no Upgrade in Manage menu
-   [ ] Renew → checkout bridge (not sso-login)
-   [ ] Connect license (empty state) still sso-login
-   [ ] New purchase still add_to_cart checkout

-   [ ] **Step 5: Link spec to plan**

In `docs/superpowers/specs/2026-07-01-license-direct-checkout-upgrade-design.md`, replace implementation plan placeholder with:

```markdown
## Implementation plan

[../plans/2026-07-01-license-direct-checkout-upgrade.md](../plans/2026-07-01-license-direct-checkout-upgrade.md)
```

-   [ ] **Step 6: Commit doc link (client repo)**

```bash
git add docs/superpowers/specs/2026-07-01-license-direct-checkout-upgrade-design.md docs/superpowers/plans/2026-07-01-license-direct-checkout-upgrade.md
git commit -m "docs: add direct checkout upgrade implementation plan"
```

---

## Spec coverage checklist

| Spec requirement                      | Task          |
| ------------------------------------- | ------------- |
| Upgrade checkout bridge URL           | Task 3        |
| Renew checkout bridge URL             | Task 3, 4     |
| Guest → checkout login (no sso-login) | Task 1, 2     |
| Pro → AA 1 + AA 5 targets             | Task 5        |
| AA 1 → AA 5 only                      | Task 5        |
| AA 5 → hide upgrade                   | Task 5        |
| Connect / buy unchanged               | Task 6 manual |
| Playwright coverage                   | Tasks 3–5     |
| Shop PHPUnit coverage                 | Tasks 1–2     |
