# Ad List Stats Query — Implementation Record

**Date:** 2026-06-18  
**Status:** Complete  
**Spec:** `docs/superpowers/specs/2026-06-18-ad-list-stats-query-design.md`

---

## Goal

Eliminate N+1 click/impression/CTR queries on the admin ad list.

---

## What was built

| Repo                  | File                                         | Change                                                     |
| --------------------- | -------------------------------------------- | ---------------------------------------------------------- |
| advanced-ads          | `includes/utilities/class-ad-list-stats.php` | New request-scoped stats registry                          |
| advanced-ads          | `includes/abstracts/abstract-ad.php`         | Accessors read registry; return `0` when unset             |
| advanced-ads          | `tests/Unit/Abstracts/AdTest.php`            | Registry + zero-fallback tests                             |
| advanced-ads          | `tests/Unit/Ads/RepositoryTest.php`          | `expiry_date` in summary expectation                       |
| advanced-ads-tracking | `includes/admin/class-ad-list-table.php`     | Fixed JOIN detection, `posts_results` priming, orderby fix |

**Not implemented (from original plan):** `AdvancedAds\Admin\Ads\Stats_Query` in core. Tracking keeps ownership of the JOIN.

---

## Verification

-   [x] Query Monitor: main query JOINs; no per-ad `SUM(count)` on render
-   [x] Sort by impressions, clicks, CTR
-   [x] `composer test -- --filter AdTest`
-   [x] `composer test -- --filter test_get_ad_summaries_returns_lightweight_fields`
-   [x] Ad list with `List_Filters` enabled (manual smoke)

---

## Commits

-   **advanced-ads:** `fix(ads): read list stats from request-scoped registry`
-   **advanced-ads-tracking:** `perf(admin): fix ad list stats JOIN and request-scoped priming`
