# License upgrade & renew — direct shop checkout

**Date:** 2026-07-01  
**Status:** Approved for implementation  
**Plugins:** `advanced-ads` (client), `advanced-ads-licenses` (shop)  
**Related:** [2026-06-18-license-post-checkout-notice-design.md](./2026-06-18-license-post-checkout-notice-design.md), [oauth-guide.md](https://github.com/advanced-ads/advanced-ads-licenses) (shop checkout intents)

## Summary

Replace **sso-login** redirects for **Upgrade plan** and **Renew license** with **direct shop checkout** URLs (same pattern as in-plugin new purchase). Guests land on the EDD checkout page and sign in there; logged-in shop customers go straight to EDD SL upgrade/renew checkout.

Constrain the upgrade pricing modal to valid upgrade paths only; hide upgrade entirely for All Access (5 sites).

## Decisions

| Topic                         | Decision                                                                                  |
| ----------------------------- | ----------------------------------------------------------------------------------------- |
| Upgrade / renew entry URL     | Shop checkout bridge with `site` + `intent` (not `sso-login`)                             |
| Guest not logged into shop    | Checkout page login form; **no** `sso-login` detour                                       |
| Logged into shop              | Immediate redirect to `sl_license_upgrade` or SL renewal URL (shop resolves `upgrade_id`) |
| `upgrade_id` in client        | **Never** hardcoded — shop resolves per `license_id` + target product/price               |
| Pro upgrade targets           | All Access (1 site), All Access (5 sites)                                                 |
| AA (1 site) upgrade targets   | All Access (5 sites) only                                                                 |
| AA (5 sites)                  | No upgrade — hide **Upgrade plan** in Manage menu                                         |
| Renew                         | Same direct-checkout pattern as upgrade                                                   |
| Connect license (empty state) | **Out of scope** — stays `sso-login`                                                      |
| New purchase (empty state)    | **Unchanged** — already `add_to_cart` checkout bridge                                     |

## Problem

Today **Manage → Upgrade plan** and **Renew license** navigate to:

```
{shop}/sso-login?site=…&intent=upgrade|renew&license_id=…&download_id=…&pricing_id=…
```

Customers expect to land on checkout (e.g. `edd_action=sl_license_upgrade`) like a normal EDD upgrade, not an intermediate SSO page.

The pricing modal also shows all three plan cards regardless of current plan, and **Upgrade plan** appears even for All Access (5 sites), which has no valid upgrade path.

## Target URLs

### Upgrade (client builds)

```
{shop}/checkout/?site={plugin_license_admin_url}&intent=upgrade&license_id={id}&download_id={id}&pricing_id={id}
```

Example after shop processing (logged-in):

```
{shop}/checkout/?edd_action=sl_license_upgrade&license_id=37063&upgrade_id=1
```

`upgrade_id` is resolved server-side by `License_Upgrade::resolve_upgrade_id_for_target()` — not sent by the client.

### Renew (client builds)

```
{shop}/checkout/?site={plugin_license_admin_url}&intent=renew&license_id={id}
```

Shop resolves to EDD SL renewal checkout URL.

### `site` parameter

Same as new purchase: full plugin license admin URL from `buildLicenseAdminUrl()`:

```
https://customer.test/wp-admin/admin.php?page=advanced-ads-app&path=%2Flicense
```

Required so the shop stores return site in EDD session and post-checkout redirect works.

## Architecture

### Flow comparison

```text
Today (upgrade):
  Plugin → sso-login → (login) → shop builds sl_license_upgrade → checkout

Target (upgrade):
  Plugin → checkout bridge (?site&intent=upgrade&…) →
    logged in:  shop builds sl_license_upgrade → checkout
    guest:      checkout login → resume pending intent → sl_license_upgrade → checkout
```

Renew follows the same guest/logged-in split with `intent=renew`.

### Upgrade matrix (UI)

| Current plan (`resolvePlanIdForLicense`) | Allowed target plan ids                | Manage menu              |
| ---------------------------------------- | -------------------------------------- | ------------------------ |
| `pro`                                    | `all-access-single`, `all-access-five` | Upgrade plan             |
| `all-access-single`                      | `all-access-five`                      | Upgrade plan             |
| `all-access-five`                        | _(none)_                               | **No** Upgrade plan item |

Pricing modal shows **only** allowed target plans (not all three cards). Current plan is not selectable.

### Product / price mapping (unchanged)

From `PricingTable` `PLANS`:

| Plan id             | downloadId | pricingId |
| ------------------- | ---------- | --------- |
| `pro`               | 1742       | 0         |
| `all-access-single` | 95170      | 1         |
| `all-access-five`   | 95170      | 2         |

## Components

### Client — `advanced-ads`

| Unit                            | File                                 | Responsibility                           |
| ------------------------------- | ------------------------------------ | ---------------------------------------- |
| `buildShopUpgradeCheckoutUrl()` | `src/admin/screen-licenses/utils.js` | Checkout bridge URL for upgrade          |
| `buildShopRenewalCheckoutUrl()` | same                                 | Checkout bridge URL for renew            |
| `startShopUpgradeForPlan()`     | same                                 | Navigate to upgrade bridge (replace SSO) |
| `startShopRenewalForLicense()`  | same                                 | Navigate to renew bridge (replace SSO)   |
| `getAllowedUpgradePlanIds()`    | same                                 | Target plan ids from current plan id     |
| `PricingTable` / `PricingModal` | components                           | Render only allowed upgrade targets      |
| `LicenseItem`                   | components                           | Hide Upgrade menu when no targets        |

**Deprecate for upgrade/renew:** `buildShopUpgradeUrl()`, `buildShopRenewalUrl()` SSO builders (or repoint to checkout bridge). `buildShopSsoUrl()` remains for Connect license.

### Shop — `advanced-ads-licenses`

| Unit                                   | File                                   | Responsibility                                                        |
| -------------------------------------- | -------------------------------------- | --------------------------------------------------------------------- |
| `maybe_handle_plugin_checkout_entry()` | `includes/checkout/class-checkout.php` | Guest upgrade/renew: stay on checkout, do not redirect to `sso-login` |
| `resume_pending_intent_after_login()`  | same                                   | Unchanged — resumes upgrade/renew after checkout login                |
| `redirect_for_upgrade_intent()`        | same                                   | Unchanged — builds `sl_license_upgrade` URL                           |
| `redirect_for_renew_intent()`          | same                                   | Unchanged — builds renewal URL                                        |
| `require_login_for_plugin_checkout`    | same                                   | Unchanged — forces login when `site` in session                       |

**Guest change (explicit):** In `maybe_handle_plugin_checkout_entry()`, when `intent` is upgrade or renew and user is not logged in:

1. `store_return_site( $site )`
2. `store_pending_intent( $context )`
3. `store_checkout_context( $intent, $license_id )`
4. Redirect to `edd_get_checkout_uri()` (not `redirect_guest_to_connect()`)

Logged-in path unchanged: immediate `redirect_for_upgrade_intent()` / `redirect_for_renew_intent()`.

## Error handling

| Case                                   | Behavior                                                                     |
| -------------------------------------- | ---------------------------------------------------------------------------- |
| No EDD upgrade path for target         | Shop redirects to plugin with `?advads_upgrade_error=unavailable` (existing) |
| Renew unavailable                      | `?advads_renew_error=unavailable` (existing)                                 |
| Invalid / missing `site` on bridge URL | Shop `wp_die` 400 (existing)                                                 |
| User picks disabled plan in modal      | Prevented by UI — only allowed targets shown                                 |

## Testing

### Playwright — `advanced-ads`

| Test                        | Expectation                                                                                                              |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Upgrade plan CTA            | Navigates to `/checkout/` with `intent=upgrade`, `license_id`, `download_id`, `pricing_id`, `site` — **not** `sso-login` |
| Renew license               | Navigates to `/checkout/` with `intent=renew`, `license_id`, `site`                                                      |
| Pro license modal           | Only All Access (1 site) and (5 sites) cards                                                                             |
| AA 1-site modal             | Only All Access (5 sites) card                                                                                           |
| AA 5-site license           | Manage menu has no **Upgrade plan**                                                                                      |
| Regression: empty-state buy | Still `add_to_cart` checkout (unchanged)                                                                                 |
| Regression: connect license | Still `sso-login` (unchanged)                                                                                            |

Update `license-manage.spec.ts`, `license-expiry-notices.spec.ts` (renew), and add `license-upgrade-checkout.spec.ts` as needed.

### PHPUnit — `advanced-ads-licenses`

| Test                            | Expectation                                                                 |
| ------------------------------- | --------------------------------------------------------------------------- |
| Guest upgrade bridge            | No redirect to `sso-login`; pending intent stored; redirect to checkout URI |
| Guest renew bridge              | Same                                                                        |
| Logged-in upgrade bridge        | Redirect to URL containing `sl_license_upgrade`                             |
| `resolve_upgrade_id_for_target` | Unchanged regression coverage                                               |

## Out of scope

-   Connect license (`sso-login` on empty state)
-   New purchase checkout from pricing modal (already direct)
-   EDD product / upgrade path configuration on the shop
-   Changing post-checkout notice copy or return URL logic
-   SSO for other intents

## Implementation plan

[../plans/2026-07-01-license-direct-checkout-upgrade.md](../plans/2026-07-01-license-direct-checkout-upgrade.md)
