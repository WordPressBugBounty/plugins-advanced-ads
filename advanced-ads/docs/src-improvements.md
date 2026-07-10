# src/ Code Improvements

Findings from a review of `src/admin/`. Ordered by priority.

---

## Bug / Correctness

### 1. `useFetch.js` always sends POST instead of GET

**File:** `src/admin/hooks/useFetch.js`

`extraArgs` defaults to `{}` which is always truthy, so the `if (extraArgs)` branch always fires — every call becomes a POST with empty data even when a GET was intended.

Fix: check `Object.keys(extraArgs).length > 0`.
Also: `extraArgs` is missing from the `useEffect` dependency array, so stale args won't trigger a re-fetch.

```js
// current (broken)
if ( extraArgs ) {
	args = { ...args, method: 'POST', data: extraArgs };
}

// fix
if ( extraArgs && Object.keys( extraArgs ).length > 0 ) {
	args = { ...args, method: 'POST', data: extraArgs };
}
// and add extraArgs to useEffect deps
```

---

### 2. Duplicate `STORE_NAME` in `licenses-api.js`

**File:** `src/admin/screen-licenses/hooks/licenses-api.js` line 6

`STORE_NAME` is hardcoded as `'advanced-ads/store'` locally instead of being imported from `@admin/store`. If the name ever changes, this copy will silently break.

```js
// remove this line
const STORE_NAME = 'advanced-ads/store';

// add to imports
import { STORE_NAME } from '@admin/store';
```

---

## Dead Code to Remove

### 3. `activateActionsDisabled` in `AddonRowActions.jsx`

**File:** `src/admin/screen-licenses/components/AddonRowActions.jsx`

`const activateActionsDisabled = false` is hardcoded and never changes. It's used as a `disabled` condition but can never actually disable anything.

Remove the constant and the `|| activateActionsDisabled` conditions that depend on it.

---

### 4. `isComponentsBusy` prop in `DownloadActivateButton.jsx`

**File:** `src/admin/screen-licenses/components/DownloadActivateButton.jsx`

`isComponentsBusy` is accepted as a prop (default `false`) and used in button label/disabled logic, but `LicenseItem.jsx` never passes it — so it is always `false`.

Remove the prop, simplify the label and `isDisabled` logic.

---

### 5. `sitesTotal` alias in `LicenseItem.jsx`

**File:** `src/admin/screen-licenses/components/LicenseItem.jsx` line 73

```js
const sitesTotal = availableSites; // unnecessary alias
```

Replace all uses of `sitesTotal` with `availableSites` directly and remove the line.

---

### 6. Unused `licenseType` prop in `LicenseItem.jsx`

**File:** `src/admin/screen-licenses/components/LicenseItem.jsx` and `License.jsx`

`licenseType` is destructured from props and passed from `License.jsx` as `licenseType={license.name}` but is never used inside `LicenseItem`. Remove the prop from both files.

---

## Simplifications

### 7. Redundant early-return in `isRichLicenseActive`

**File:** `src/admin/screen-licenses/utils.js`

The negative-check block is unnecessary — the final `return` already returns `false` for anything that isn't `'active'` or `'valid'`.

```js
// current — the if-block adds no value
if ( normalized === 'expired' || normalized === 'inactive' || ... ) {
    return false;
}
return normalized === 'active' || normalized === 'valid';

// fix
return normalized === 'active' || normalized === 'valid';
```

---

### 8. `Date.now()` called twice + repeated magic number

**File:** `src/admin/screen-licenses/utils.js`

`isLicenseExpiringSoon` and `getDaysUntilLicenseExpiry` both call `Date.now()` twice in the same function (once for the guard, once for the math). Capture it once. Also, `24 * 60 * 60 * 1000` is repeated — extract to a module-level constant.

```js
const MS_PER_DAY = 24 * 60 * 60 * 1000;

export function isLicenseExpiringSoon( expiryDate, days = 30 ) {
	const ts = getLicenseExpiryTimestamp( expiryDate );
	const now = Date.now();
	if ( ! ts || ts <= now ) return false;
	return ts - now <= days * MS_PER_DAY;
}
```

---

### 9. Double lookup table in `addonIdForLicenseProductName`

**File:** `src/admin/screen-licenses/utils.js`

The function has two arrays — `manifest` and `labels` — that encode the same addon-name-to-id mapping with overlapping entries. Consolidate into one unified lookup object.

---

### 10. Repeated `safeObject` pattern in `normalizeLicensesResponse`

**File:** `src/admin/screen-licenses/hooks/licenses-api.js`

The same 3-line guard appears 4 times:

```js
payload?.foo && typeof payload.foo === 'object' ? payload.foo : {};
```

Extract a helper:

```js
function safeObject( val ) {
	return val && typeof val === 'object' ? val : {};
}
```

---

### 11. Two patterns for getting the license list in `AddonRowActions.jsx`

**File:** `src/admin/screen-licenses/components/AddonRowActions.jsx`

`getLicenseListForSave()` calls `select(STORE_NAME)` imperatively, while `handleDeactivate` uses the `licensesFromStore` value from `useSelect`. They do the same thing. Remove `getLicenseListForSave` and use `licensesFromStore` consistently.

---

### 12. Duplicate `pricingId` validation in two files

**Files:** `src/admin/screen-licenses/utils.js` and `components/PricingTable.jsx`

The check `plan.id === 'all-access-five' && pricingId === 2` appears in both `getCheckoutIdsForPlan` (utils.js) and the `checkoutReady` condition in `PricingTable.jsx`. Since `getCheckoutIdsForPlan` already enforces it, remove the redundant check from `PricingTable.jsx`.

---

## Complexity

### 13. Break up `getDisplayLicenseStatus`

**File:** `src/admin/screen-licenses/utils.js`

This function is 90+ lines with deeply nested branches. It also has two `// eslint-disable-next-line @wordpress/no-unused-vars-before-return` comments because variables are computed before early returns — a sign the logic isn't well structured.

Break it into named sub-functions (e.g. `getDisplayStatusForAllAccess`, `getDisplayStatusWithAppliedMap`) so each path is readable on its own and the eslint suppression can be removed.
