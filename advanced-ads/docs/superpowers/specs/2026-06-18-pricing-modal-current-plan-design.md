# Pricing modal — disable current plan button

**Date:** 2026-06-18  
**Status:** Approved  
**Scope:** UI only — upgrade modal from license card

## Goal

When opening **Upgrade plan**, the pricing card that matches the user's current license must show a **grey disabled** CTA with the **original label unchanged** (e.g. "Upgrade to Pro").

## Plan mapping

| License              | `currentPlanId`     |
| -------------------- | ------------------- |
| Pro product          | `pro`               |
| All Access, 1 site   | `all-access-single` |
| All Access, 5+ sites | `all-access-five`   |
| Unknown / other      | `null`              |

`resolvePlanIdForLicense( license )` uses `isAllAccessBundleName`, `availableSites`, and `addonIdForLicenseProductName`.

## UI

Disabled current-plan button (and checkout-not-ready):

-   `bg-gray-200 text-gray-500 cursor-not-allowed`
-   No hover
-   `disabled`, no click handler

## Data flow

`LicenseItem` → `PricingModal` → `PricingTable` with `currentPlanId`.

`ctaDisabled = ! checkoutReady || plan.id === currentPlanId`

Empty state buy modal: no `currentPlanId` — all cards behave as today.

## Files

-   `utils.js` — `resolvePlanIdForLicense`
-   `LicenseItem.jsx` — pass `currentPlanId`
-   `PricingModal.jsx` — forward prop
-   `PricingTable.jsx` — disable logic
-   `PricingTableItem.jsx` — grey disabled styles

## Acceptance

-   [ ] Pro license: Pro card button grey/disabled; other cards active
-   [ ] All Access single: middle card disabled
-   [ ] All Access 5 sites: right card disabled
-   [ ] Labels unchanged when disabled
-   [ ] Empty state modal unchanged
