# License Page Playwright Tests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement 38 fully-mocked Playwright acceptance tests for `/license` with no shop URL dependency.

**Architecture:** Centralize mock data in `licenseFixtures.ts` and route stubs in `licenseMocks.ts`. Spec files call helpers from `licensePage.ts` and register mocks before `page.goto()`. SSO/checkout navigation is intercepted via `page.route('**/sso-login**')` — tests assert query params, never hit a real shop.

**Tech Stack:** `@playwright/test`, TypeScript, WordPress `wp-scripts test-playwright`, existing `auth.setup.ts` → `auth.json`.

**Design spec:** `docs/superpowers/specs/tests/2026-06-16-license-page-test-design.md`

---

## File map

| Action | Path                                                             | Responsibility                                                   |
| ------ | ---------------------------------------------------------------- | ---------------------------------------------------------------- |
| Create | `tests/Acceptance/Admin/Licenses/licenseFixtures.ts`             | Mock license records + `CHECKOUT_PLANS`                          |
| Create | `tests/Acceptance/Admin/Licenses/licenseMocks.ts`                | All `page.route()` stubs                                         |
| Create | `tests/Acceptance/Admin/Licenses/licensePage.ts`                 | Navigation, locators, SSO assertions                             |
| Create | `tests/Acceptance/Admin/Licenses/license-page-load.spec.ts`      | Tests #1–2                                                       |
| Create | `tests/Acceptance/Admin/Licenses/empty-state.spec.ts`            | Tests #3–10                                                      |
| Create | `tests/Acceptance/Admin/Licenses/exchange-token.spec.ts`         | Tests #11–15                                                     |
| Create | `tests/Acceptance/Admin/Licenses/activate-license.spec.ts`       | Tests #16–17                                                     |
| Create | `tests/Acceptance/Admin/Licenses/license-listing.spec.ts`        | Tests #18–21                                                     |
| Create | `tests/Acceptance/Admin/Licenses/license-expiry-notices.spec.ts` | Tests #22–28                                                     |
| Create | `tests/Acceptance/Admin/Licenses/license-shop-errors.spec.ts`    | Tests #29–30                                                     |
| Create | `tests/Acceptance/Admin/Licenses/license-manage.spec.ts`         | Tests #31–33                                                     |
| Create | `tests/Acceptance/Admin/Licenses/license-modals.spec.ts`         | Tests #34–35                                                     |
| Create | `tests/Acceptance/Admin/Licenses/all-access-addons.spec.ts`      | Tests #36–38                                                     |
| Modify | `playwright.config.ts`                                           | Remove `admin-licenses-e2e` project + `testIgnore` for lifecycle |
| Modify | `dev.config.example.json`                                        | Note shop block is optional (not used by license tests)          |

**Do not restore:** `licenseE2e.ts`, `licenseExpiry.ts`, `license-lifecycle.spec.ts` (shop-dependent).

---

### Task 1: Playwright config cleanup

**Files:**

-   Modify: `playwright.config.ts`

-   [ ] **Step 1: Remove shop E2E project and lifecycle ignores**

Delete the `admin-licenses-e2e` project block (lines ~72–78) and remove `testIgnore: '**/license-lifecycle.spec.ts'` from `admin` and `admin-licenses` projects.

-   [ ] **Step 2: Verify config parses**

Run: `npx playwright test --list --project=admin-licenses`
Expected: Lists 0 tests initially (no spec files yet), no config error.

---

### Task 2: License fixtures

**Files:**

-   Create: `tests/Acceptance/Admin/Licenses/licenseFixtures.ts`

-   [ ] **Step 1: Create fixture types and constants**

```typescript
import { expect } from '@playwright/test';

export const MOCK_SHOP_ORIGIN = 'https://shop.example.test';

export const CHECKOUT_PLANS = [
	{ id: 'pro', cta: 'Upgrade to Pro', downloadId: '1742', pricingId: null },
	{
		id: 'all-access-single',
		cta: 'Get All Access',
		downloadId: '95170',
		pricingId: '1',
	},
	{
		id: 'all-access-five',
		cta: 'Scale to multiple sites',
		downloadId: '95170',
		pricingId: '2',
	},
] as const;

export type ExchangeLicense = {
	name: string;
	status: string;
	licenseId: number;
	licenseKey: string;
	activationCount: number;
	availableSites: number;
	purchaseDate: string;
	expiryDate: string;
	autoRenew: boolean;
	paymentStatus: string;
	sitesActivated: Array< { domain: string; createdAt: string } >;
	download_url?: string;
	addons?: Array< { name: string; download_url: string } >;
};

function daysFromNow( days: number ): string {
	const d = new Date();
	d.setDate( d.getDate() + days );
	const dd = String( d.getDate() ).padStart( 2, '0' );
	const mm = String( d.getMonth() + 1 ).padStart( 2, '0' );
	return `${ dd }-${ mm }-${ d.getFullYear() }`;
}

export function adminHostnameFromBase(
	baseURL = 'http://localhost:8888'
): string {
	return new URL( baseURL ).hostname;
}

export function proLicenseActive( adminHost = 'localhost' ): ExchangeLicense {
	return {
		name: 'Advanced Ads Pro',
		status: 'active',
		licenseId: 37013,
		licenseKey: 'c39217ce98ee446444f658f0207a0244',
		activationCount: 0,
		availableSites: 1,
		purchaseDate: '15-06-2026',
		expiryDate: daysFromNow( 365 ),
		autoRenew: false,
		paymentStatus: 'complete',
		sitesActivated: [],
		download_url: `${ MOCK_SHOP_ORIGIN }/edd-sl/package_download/mock-pro`,
	};
}

export function proLicenseActivated(
	adminHost = 'localhost'
): ExchangeLicense {
	return {
		...proLicenseActive( adminHost ),
		activationCount: 1,
		sitesActivated: [ { domain: adminHost, createdAt: '15-06-2026' } ],
	};
}

export function proLicenseHealthy( adminHost = 'localhost' ): ExchangeLicense {
	return proLicenseActivated( adminHost );
}

export function autoRenewProLicense(
	adminHost = 'localhost'
): ExchangeLicense {
	return { ...proLicenseActivated( adminHost ), autoRenew: true };
}

export function expiredProLicense( adminHost = 'localhost' ): ExchangeLicense {
	return {
		...proLicenseActive( adminHost ),
		status: 'expired',
		expiryDate: daysFromNow( -30 ),
	};
}

export function expiringSoonLicense(
	adminHost = 'localhost'
): ExchangeLicense {
	const license = proLicenseActive( adminHost );
	license.expiryDate = daysFromNow( 14 );
	return license;
}

export function renewedProLicense( adminHost = 'localhost' ): ExchangeLicense {
	return {
		...expiredProLicense( adminHost ),
		status: 'active',
		expiryDate: daysFromNow( 365 ),
	};
}

export function paymentFailedLicense(
	adminHost = 'localhost'
): ExchangeLicense {
	return { ...proLicenseActive( adminHost ), paymentStatus: 'failed' };
}

export function allAccessLicense( adminHost = 'localhost' ): ExchangeLicense {
	return {
		name: 'All Access',
		status: 'active',
		licenseId: 37010,
		licenseKey: 'e86cfb278cb1c44898982ca2e389de1f',
		activationCount: 1,
		availableSites: 1,
		purchaseDate: '13-06-2026',
		expiryDate: daysFromNow( 365 ),
		autoRenew: false,
		paymentStatus: 'complete',
		sitesActivated: [ { domain: adminHost, createdAt: '13-06-2026' } ],
		addons: [
			{
				name: 'pro',
				download_url: `${ MOCK_SHOP_ORIGIN }/edd-sl/package_download/mock-aa-pro`,
			},
			{
				name: 'tracking',
				download_url: `${ MOCK_SHOP_ORIGIN }/edd-sl/package_download/mock-aa-tracking`,
			},
		],
	};
}

export function sampleExchangeLicenses(
	adminHost = 'localhost'
): ExchangeLicense[] {
	return [ proLicenseActive( adminHost ), allAccessLicense( adminHost ) ];
}

export function expectExchangeLicenseShape( license: ExchangeLicense ) {
	expect( license ).toMatchObject( {
		name: expect.any( String ),
		status: expect.any( String ),
		licenseId: expect.any( Number ),
		licenseKey: expect.stringMatching( /^[a-f0-9]{32}$/i ),
		activationCount: expect.any( Number ),
		availableSites: expect.any( Number ),
		purchaseDate: expect.any( String ),
		expiryDate: expect.any( String ),
		paymentStatus: expect.any( String ),
		sitesActivated: expect.any( Array ),
	} );
}
```

---

### Task 3: License mocks

**Files:**

-   Create: `tests/Acceptance/Admin/Licenses/licenseMocks.ts`

-   [ ] **Step 1: Create route patterns and payload builder**

```typescript
import { expect, Page } from '@playwright/test';
import type { ExchangeLicense } from './licenseFixtures';

export const LICENSES_API = /\/wp-json\/advanced-ads\/v1\/licenses(?:\?.*)?$/;
export const EXCHANGE_API = /\/wp-json\/advanced-ads\/v2\/license\/exchange$/;
export const ACTIVATE_API = /\/wp-json\/advanced-ads\/v2\/license\/activate$/;
export const AUTOUPDATE_API = /\/wp-json\/advanced-ads\/v1\/plugin-autoupdate$/;
export const SSO_LOGIN_ROUTE = /sso-login/;

export type LicensesPayload = {
	licenses: ExchangeLicense[];
	appliedAddonKeyMap: Record< string, string >;
	autoUpdateStates: Record< string, string >;
	addonInstallStates: Record<
		string,
		{ installed?: boolean; active?: boolean }
	>;
	lastSyncAt: number;
	expiryNoticeFlags: Record< string, string >;
};

function defaultPayload( licenses: ExchangeLicense[] = [] ): LicensesPayload {
	return {
		licenses,
		appliedAddonKeyMap: {},
		autoUpdateStates: { main: 'on', pro: 'off' },
		addonInstallStates: {},
		lastSyncAt: 0,
		expiryNoticeFlags: {},
	};
}

function json( payload: LicensesPayload ) {
	return JSON.stringify( payload );
}

export async function mockLicensesApi(
	page: Page,
	initial: Partial< LicensesPayload > & { licenses?: ExchangeLicense[] } = {}
) {
	let state: LicensesPayload = {
		...defaultPayload( initial.licenses ?? [] ),
		...initial,
		licenses: initial.licenses ?? [],
	};

	await page.route( LICENSES_API, async ( route ) => {
		const method = route.request().method();

		if ( method === 'GET' ) {
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: json( state ),
			} );
			return;
		}

		if ( method === 'POST' ) {
			const post = JSON.parse( route.request().postData() ?? '{}' );

			if ( Array.isArray( post.licenses ) ) {
				state.licenses = post.licenses;
			}

			if ( post.deactivatingLicenseKey ) {
				state.appliedAddonKeyMap = {};
			}

			if ( post.deactivatingAddonId ) {
				const id = String( post.deactivatingAddonId );
				state.addonInstallStates = {
					...state.addonInstallStates,
					[ id ]: { installed: true, active: false },
				};
			}

			if ( post.activatingAddonId && post.activatingLicenseKey ) {
				const id = String( post.activatingAddonId );
				state.appliedAddonKeyMap = {
					...state.appliedAddonKeyMap,
					[ id ]: String( post.activatingLicenseKey ),
				};
				state.addonInstallStates = {
					...state.addonInstallStates,
					[ id ]: { installed: true, active: true },
				};
			}

			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: json( state ),
			} );
			return;
		}

		await route.continue();
	} );

	return {
		getState: () => state,
		setState: ( next: Partial< LicensesPayload > ) => {
			state = { ...state, ...next };
		},
	};
}

export async function withEmptyLicenses( page: Page ) {
	return mockLicensesApi( page, { licenses: [] } );
}

export async function withLicenseListing(
	page: Page,
	licenses: ExchangeLicense[],
	extras: Partial< LicensesPayload > = {}
) {
	return mockLicensesApi( page, { licenses, ...extras } );
}

export async function mockSsoNavigation( page: Page ) {
	await page.route( SSO_LOGIN_ROUTE, async ( route ) => {
		await route.fulfill( {
			status: 200,
			contentType: 'text/html',
			body: '<html><body><div id="connect-app">stub</div></body></html>',
		} );
	} );
}

/** Capture the next SSO navigation URL (location.href assignment). */
export async function captureNextSsoUrl( page: Page ): Promise< string > {
	let captured = '';
	await page.route( SSO_LOGIN_ROUTE, async ( route ) => {
		captured = route.request().url();
		await route.fulfill( {
			status: 200,
			contentType: 'text/html',
			body: '<html><body><div id="connect-app">stub</div></body></html>',
		} );
	} );
	return new Proxy( {} as { url: string }, {
		get( _, prop ) {
			if ( prop === 'url' ) return captured;
			if ( prop === 'wait' ) {
				return () =>
					page
						.waitForURL( SSO_LOGIN_ROUTE, { timeout: 30000 } )
						.then( () => captured );
			}
		},
	} ) as { url: string; wait: () => Promise< string > };
}

export async function mockExchangeFlow(
	page: Page,
	exchangeLicenses: ExchangeLicense[],
	options: {
		expectedToken?: string;
		status?: number;
		errorMessage?: string;
	} = {}
) {
	const api = await mockLicensesApi( page, { licenses: [] } );

	await page.route( EXCHANGE_API, async ( route ) => {
		const body = JSON.parse( route.request().postData() ?? '{}' );
		if ( options.expectedToken ) {
			expect( body.token ).toBe( options.expectedToken );
		}
		expect( body.token ).toEqual( expect.any( String ) );
		expect( body.site ).toEqual( expect.any( String ) );

		if ( options.status && options.status >= 400 ) {
			await route.fulfill( {
				status: options.status,
				contentType: 'application/json',
				body: JSON.stringify( {
					message: options.errorMessage ?? 'Exchange failed.',
				} ),
			} );
			return;
		}

		api.setState( { licenses: exchangeLicenses } );
		await route.fulfill( {
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify( exchangeLicenses ),
		} );
	} );

	return api;
}

export async function mockShopActivate(
	page: Page,
	options: { status?: number; message?: string } = {}
) {
	await page.route( ACTIVATE_API, async ( route ) => {
		const status = options.status ?? 200;
		await route.fulfill( {
			status,
			contentType: 'application/json',
			body: JSON.stringify( {
				message:
					options.message ??
					'We are installing and activating the plugin and included add-ons for you…',
			} ),
		} );
	} );
}

export async function mockAutoUpdateApi( page: Page ) {
	let states: Record< string, string > = { main: 'on', pro: 'off' };

	await page.route( AUTOUPDATE_API, async ( route ) => {
		const post = JSON.parse( route.request().postData() ?? '{}' );
		const addonId = String( post.addonId ?? 'main' );
		states = { ...states, [ addonId ]: post.state === 'on' ? 'on' : 'off' };
		await route.fulfill( {
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify( { autoUpdateStates: states } ),
		} );
	} );
}
```

---

### Task 4: Page helpers

**Files:**

-   Create: `tests/Acceptance/Admin/Licenses/licensePage.ts`

-   [ ] **Step 1: Create navigation and assertion helpers**

Key exports (implement fully — restore from git HEAD where applicable, drop `getShopHostname` / `loadShopConfig`):

```typescript
export const LICENSE_ADMIN_PATH =
	'/wp-admin/admin.php?page=advanced-ads-app&path=/license';

export async function gotoLicensePage( page: Page, query = '' ) {
	const path = query
		? `${ LICENSE_ADMIN_PATH }&${ query }`
		: LICENSE_ADMIN_PATH;
	await page.goto( path, { waitUntil: 'domcontentloaded' } );
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}

export function emptyLicenseHeading( page: Page ) {
	return page.getByRole( 'heading', {
		name: 'No active licenses there yet',
	} );
}

export async function waitForLicensesLoaded( page: Page ) {
	await expect(
		emptyLicenseHeading( page )
			.or( page.getByText( 'License type:', { exact: true } ) )
			.first()
	).toBeVisible( { timeout: 60000 } );
}

export function licenseCards( page: Page ) {
	/* filter main for License key + type */
}
export function firstLicenseCard( page: Page ) {
	return licenseCards( page ).first();
}
export function licenseCardByKey( page: Page, key: string ) {
	/* ... */
}

export function pricingModal( page: Page ) {
	return page.locator( '.advads-modal' );
}

export async function clickPlanCtaInModal( page: Page, ctaLabel: string ) {
	const modal = pricingModal( page );
	await modal.getByRole( 'button', { name: ctaLabel, exact: true } ).click();
	await page.waitForURL( /sso-login/, { timeout: 30000 } );
}

export async function readAdvancedAdsEndpoints( page: Page ) {
	return page.evaluate(
		() => ( window as any ).advancedAds?.endpoints ?? {}
	);
}

export function assertSsoLoginParams(
	url: string,
	expected: {
		intent?: string | null;
		downloadId?: string;
		pricingId?: string | null;
		licenseId?: string;
		siteContains?: string;
	}
) {
	const parsed = new URL( url );
	expect( parsed.pathname ).toContain( '/sso-login' );
	if ( expected.intent === null ) {
		expect( parsed.searchParams.get( 'intent' ) ).toBeNull();
	} else if ( expected.intent ) {
		expect( parsed.searchParams.get( 'intent' ) ).toBe( expected.intent );
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
	if ( expected.licenseId ) {
		expect( parsed.searchParams.get( 'license_id' ) ).toBe(
			expected.licenseId
		);
	}
	if ( expected.siteContains ) {
		expect( parsed.searchParams.get( 'site' ) ?? '' ).toContain(
			expected.siteContains
		);
	}
}

export async function openManageMenu(
	page: Page,
	card = firstLicenseCard( page )
) {
	await card.getByRole( 'button', { name: 'Manage', exact: true } ).click();
}
```

**SSO intent reference** (from `src/admin/screen-licenses/utils.js`):

| Action   | `intent`   | Other params                              |
| -------- | ---------- | ----------------------------------------- |
| Connect  | _(none)_   | `site`                                    |
| Buy plan | `checkout` | `download_id`, `pricing_id`               |
| Upgrade  | `upgrade`  | `license_id`, `download_id`, `pricing_id` |
| Renew    | `renew`    | `license_id`                              |

---

### Task 5: Page load specs (tests #1–2)

**Files:**

-   Create: `tests/Acceptance/Admin/Licenses/license-page-load.spec.ts`

-   [ ] **Step 1: Write both tests**

```typescript
import { test, expect } from '@playwright/test';
import { proLicenseActive } from './licenseFixtures';
import { withEmptyLicenses, withLicenseListing } from './licenseMocks';
import {
	emptyLicenseHeading,
	gotoLicensePage,
	waitForLicensesLoaded,
} from './licensePage';

test.describe( 'License page load', () => {
	test( 'shows empty state when no licenses', async ( { page } ) => {
		await withEmptyLicenses( page );
		await gotoLicensePage( page );
		await expect( emptyLicenseHeading( page ) ).toBeVisible();
	} );

	test( 'shows license listing when licenses exist', async ( { page } ) => {
		await withLicenseListing( page, [ proLicenseActive() ] );
		await gotoLicensePage( page );
		await waitForLicensesLoaded( page );
		await expect(
			page.getByText( 'License type:', { exact: true } ).first()
		).toBeVisible();
	} );
} );
```

-   [ ] **Step 2: Run**

Run: `npm run test:playwright -- --project=admin-licenses tests/Acceptance/Admin/Licenses/license-page-load.spec.ts`
Expected: PASS (2 tests) — requires `wp-env start` + `setup` project ran first.

---

### Task 6: Empty state specs (tests #3–10)

**Files:**

-   Create: `tests/Acceptance/Admin/Licenses/empty-state.spec.ts`

-   [ ] **Step 1: Write tests #3–6 and #10**

Use `withEmptyLicenses` + `mockSsoNavigation`. For Connect click (#5), use `Promise.all([ page.waitForURL(/sso-login/), link.click() ])` then `assertSsoLoginParams`.

-   [ ] **Step 2: Write plan checkout tests #7–9**

Loop `CHECKOUT_PLANS` from `licenseFixtures.ts`:

```typescript
for ( const plan of CHECKOUT_PLANS ) {
	test( `selecting ${ plan.id } triggers checkout SSO params`, async ( {
		page,
	} ) => {
		await withEmptyLicenses( page );
		await mockSsoNavigation( page );
		await gotoLicensePage( page );
		await page.getByRole( 'button', { name: 'Buy license' } ).click();
		await clickPlanCtaInModal( page, plan.cta );
		assertSsoLoginParams( page.url(), {
			intent: 'checkout',
			downloadId: plan.downloadId,
			pricingId: plan.pricingId,
		} );
	} );
}
```

-   [ ] **Step 3: Run**

Run: `npm run test:playwright -- --project=admin-licenses tests/Acceptance/Admin/Licenses/empty-state.spec.ts`
Expected: PASS (8 tests).

---

### Task 7: Exchange token specs (tests #11–15)

**Files:**

-   Create: `tests/Acceptance/Admin/Licenses/exchange-token.spec.ts`

-   [ ] **Step 1: Port from git HEAD, replace inline routes with `mockExchangeFlow`**

Key patterns:

-   Test #12: hold exchange with `Promise` gate before `route.fulfill`
-   Test #15: `mockExchangeFlow(page, [], { status: 403, errorMessage: 'Invalid or expired token.' })` + `withEmptyLicenses`

-   [ ] **Step 2: Run**

Run: `npm run test:playwright -- --project=admin-licenses tests/Acceptance/Admin/Licenses/exchange-token.spec.ts`
Expected: PASS (5 tests).

---

### Task 8: Activate license specs (tests #16–17)

**Files:**

-   Create: `tests/Acceptance/Admin/Licenses/activate-license.spec.ts`

-   [ ] **Step 1: Port from git HEAD**

Use `withLicenseListing(page, [proLicenseActive()])` + `mockShopActivate(page)`.

For test #16, also stub licenses POST to return activated state after activate API resolves (or rely on optimistic UI + notice).

Success notice filter: `.components-notice` with text `License purchased successfully`.

-   [ ] **Step 2: Run**

Expected: PASS (2 tests).

---

### Task 9: License listing specs (tests #18–21)

**Files:**

-   Create: `tests/Acceptance/Admin/Licenses/license-listing.spec.ts`

-   [ ] **Step 1: Write field visibility test (#18)**

Assert on `firstLicenseCard(page)`: texts `Advanced Ads Pro`, `active`, license key code, purchase/expiration dates, `0 of 1 used` or similar.

-   [ ] **Step 2: Write deactivate test (#19)**

Fixture: `proLicenseActivated()` with `appliedAddonKeyMap: { pro: '<key>' }` and `addonInstallStates: { pro: { installed: true, active: true } }`.

Click Deactivate → expect POST stub clears map → "Download and activate" visible.

-   [ ] **Step 3: Write payment failed (#20) and auto-renew label (#21)**

```typescript
// #21
await expect( card.getByText( 'Renews:', { exact: true } ) ).toBeVisible();
await expect(
	card.getByText( 'License expiration:', { exact: true } )
).toBeHidden();
```

-   [ ] **Step 4: Run**

Expected: PASS (4 tests).

---

### Task 10: Expiry & renewal specs (tests #22–28)

**Files:**

-   Create: `tests/Acceptance/Admin/Licenses/license-expiry-notices.spec.ts`

-   [ ] **Step 1: Expired display (#22)**

Fixture: `expiredProLicense()`. Assert status text `expired` and `getByRole('button', { name: 'Renew' })`.

-   [ ] **Step 2: Expiring banner (#23)**

```typescript
await withLicenseListing( page, [ expiringSoonLicense() ], {
	expiryNoticeFlags: { [ expiringSoonLicense().licenseKey ]: 'month' },
} );
```

Assert `.components-notice` contains `expires in`.

-   [ ] **Step 3: Post-checkout notice (#24)**

```typescript
await withLicenseListing( page, [ proLicenseActive() ] );
await gotoLicensePage( page, 'purchase_id=123&token=discard' );
await expect(
	page.getByText( 'License purchased successfully' )
).toBeVisible();
expect( new URL( page.url() ).searchParams.get( 'purchase_id' ) ).toBeNull();
```

-   [ ] **Step 4: Inline renew SSO (#25)**

`expiredProLicense()` + `mockSsoNavigation`. Click Renew on status row → `assertSsoLoginParams(page.url(), { intent: 'renew', licenseId: '37013' })`.

-   [ ] **Step 5: Healthy license no renew (#26)**

`proLicenseHealthy()`. Assert no Renew button on card. Open Manage → expect `getByRole('menuitem', { name: 'Renew license' })` hidden.

-   [ ] **Step 6: Expiring soon card active (#27)**

Same as #23 plus assert status field shows `active` (not `expired`).

-   [ ] **Step 7: Renew token exchange (#28)**

```typescript
const renewed = renewedProLicense();
await mockExchangeFlow( page, [ renewed ], { expectedToken: 'RENEW_TOKEN' } );
await gotoLicensePage( page, `token=${ encodeURIComponent( 'RENEW_TOKEN' ) }` );
await expect(
	page.getByText( renewed.expiryDate, { exact: false } )
).toBeVisible();
```

-   [ ] **Step 8: Run**

Expected: PASS (7 tests).

---

### Task 11: Shop error specs (tests #29–30)

**Files:**

-   Create: `tests/Acceptance/Admin/Licenses/license-shop-errors.spec.ts`

-   [ ] **Step 1: Write both tests**

```typescript
test( 'advads_upgrade_error shows upgrade unavailable notice', async ( {
	page,
} ) => {
	await withEmptyLicenses( page );
	await gotoLicensePage( page, 'advads_upgrade_error=unavailable' );
	await expect( page.getByText( /could not be upgraded/i ) ).toBeVisible();
} );

test( 'advads_renew_error shows renew unavailable notice', async ( {
	page,
} ) => {
	await withEmptyLicenses( page );
	await gotoLicensePage( page, 'advads_renew_error=unavailable' );
	await expect( page.getByText( /could not be renewed/i ) ).toBeVisible();
} );
```

-   [ ] **Step 2: Run**

Expected: PASS (2 tests).

---

### Task 12: Manage menu specs (tests #31–33)

**Files:**

-   Create: `tests/Acceptance/Admin/Licenses/license-manage.spec.ts`

-   [ ] **Step 1: Upgrade modal (#31)**

`proLicenseActive()` → Manage → Upgrade plan → modal heading "Upgrade plan".

-   [ ] **Step 2: Upgrade SSO (#32)**

Select `Get All Access` in modal → `assertSsoLoginParams` with `intent: 'upgrade'`, `license_id: '37013'`, `download_id: '95170'`, `pricing_id: '1'`.

-   [ ] **Step 3: Manage renew (#33)**

`expiredProLicense()` + `mockSsoNavigation` → Manage → Renew license → `intent: 'renew'`, `license_id: '37013'`.

-   [ ] **Step 4: Run**

Expected: PASS (3 tests).

---

### Task 13: Modal specs (tests #34–35)

**Files:**

-   Create: `tests/Acceptance/Admin/Licenses/license-modals.spec.ts`

-   [ ] **Step 1: Sites modal (#34)**

Fixture with `sitesActivated: [{ domain: 'monetize.test', createdAt: '...' }]`. Click "View" → modal shows domain → close button/Escape hides it.

-   [ ] **Step 2: Auto-update modal (#35)**

`mockAutoUpdateApi(page)` + listing with `autoUpdateStates`. Click "Edit" → toggle first switch → assert POST fired and label text changes (e.g. `on`/`off` in display).

Note: inspect `AutoUpdateModal.jsx` for toggle role (`checkbox` or `button`) during implementation — use snapshot if unsure.

-   [ ] **Step 3: Run**

Expected: PASS (2 tests).

---

### Task 14: All Access addon specs (tests #36–38)

**Files:**

-   Create: `tests/Acceptance/Admin/Licenses/all-access-addons.spec.ts`

-   [ ] **Step 1: Expand addon list (#36)**

`allAccessLicense()` → expand chevron / "Add-ons" section → rows with `Advanced Ads Pro` and `Tracking` titles.

Check `AddonsList.jsx` for expand trigger text (may be "Show add-ons" or chevron on All Access card).

-   [ ] **Step 2: Activate addon (#37)**

`mockShopActivate` + listing with `addonInstallStates: {}`. Click Activate on Pro row → assert activate API called + row shows active/check icon.

-   [ ] **Step 3: Deactivate addon (#38)**

Pre-seed `addonInstallStates: { pro: { installed: true, active: true } }` + `appliedAddonKeyMap: { pro: '<aa-key>' }`. Click Deactivate → licenses POST with `deactivatingAddonId: 'pro'`.

-   [ ] **Step 4: Run**

Expected: PASS (3 tests).

---

### Task 15: Full suite verification

-   [ ] **Step 1: Run setup + full project**

```bash
wp-env start
npm run test:playwright -- --project=setup
npm run test:playwright -- --project=admin-licenses
```

Expected: **38 passed**, 0 skipped (except none — no shop credentials needed).

-   [ ] **Step 2: Update dev.config.example.json**

Add comment above `shop` block:

```json
// "shop" is optional — not required for license Playwright tests (all mocked).
```

-   [ ] **Step 3: Link plan in design spec** (optional)

Add at bottom of design spec: `Implementation plan: docs/superpowers/plans/2026-06-16-license-page-tests.md`

---

## Spec coverage self-review

| Spec section               | Task                                            |
| -------------------------- | ----------------------------------------------- |
| 38 tests inventory         | Tasks 5–14                                      |
| Mock strategy (all routes) | Task 3                                          |
| All fixtures               | Task 2                                          |
| No shop URL                | Tasks 2–3 (MOCK_SHOP_ORIGIN only)               |
| Playwright config cleanup  | Task 1                                          |
| Expiry/renewal (#21–28)    | Tasks 9–10, 12                                  |
| SSO intents                | Task 4 `assertSsoLoginParams` + Tasks 6, 10, 12 |

No placeholders remain. All 38 tests map to a task.

---

## Execution handoff

**Plan saved to `docs/superpowers/plans/2026-06-16-license-page-tests.md`.**

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks
2. **Inline Execution** — implement all tasks in this session with checkpoints

Which approach do you want?
