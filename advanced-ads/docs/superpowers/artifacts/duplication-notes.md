# Duplication signals (Wave B — structural)

## 1. Entity abstraction family

High churn and parallel structure across:

-   `includes/abstracts/abstract-ad.php`
-   `includes/abstracts/abstract-group.php`
-   `includes/abstracts/abstract-placement.php`
-   `includes/abstracts/abstract-data.php`

**Observation:** Shared patterns (lifecycle, meta, repositories) are expected, but repeated boilerplate across entities increases the cost of consistent security and API changes. **Recommendation:** When touching one abstract, scan siblings for the same pattern and add a shared trait or small service only if a second identical change is required (YAGNI until second use).

## 2. Function barrels

`includes/functions-ad.php`, `includes/functions-group.php`, `includes/functions-placement.php` show similar churn (15 commits in 24m in snapshot). **Recommendation:** Prefer thin wrappers delegating to classes already covered by PHPUnit (`tests/Unit/*`) to shrink global surface.

## 3. Display / frontend checks

`classes/display-conditions.php` and `classes/frontend_checks.php` are large and churned. **Recommendation:** Any new condition type should ship with a **PHPUnit** case near `tests/Unit/Core/` patterns before refactors.

## 4. Build artifact noise

`assets/dist/*.asset.php` (1 line each) appear in churn metrics because build commits touch them. **Recommendation:** Optionally exclude `assets/dist/*.asset.php` from future metrics export, or filter in reports, so “top churn” highlights source files.

## Automated clone detection

No PMD-CPD / jscpd run in this pass. **Next step:** Add optional `npm`/`composer` duplicate-finder job in CI if the team wants numeric duplication debt metrics.
