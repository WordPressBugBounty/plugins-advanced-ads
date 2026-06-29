# Ad list stats query — design spec

**Date:** 2026-06-18  
**Status:** Implemented  
**Scope:** Eliminate N+1 click/impression/CTR queries on the admin ad list (`edit.php?post_type=advanced_ads`).

---

## 1. Problem statement

On the ad listing page, the tracking add-on renders Statistics, Impressions, Clicks, and CTR columns. Each cell calls `Ad::get_clicks()`, `get_impressions()`, and `get_ctr()`.

Originally those methods used `get_post()` without an ID and fell back to per-ad SQL:

```sql
SELECT SUM(count) FROM {prefix}advads_clicks WHERE ad_id = ?
SELECT SUM(count) FROM {prefix}advads_impressions WHERE ad_id = ?
```

**Observed:** ~4 impression + ~4 click queries per ad per page load.

The tracking add-on already JOINed stats in the main query, but WordPress reloads plain `wp_posts` rows via `array_map( 'get_post', $posts )` after `posts_results`, dropping joined fields.

---

## 2. Success criteria

| Criterion                                    | Verification                                              | Status |
| -------------------------------------------- | --------------------------------------------------------- | ------ |
| Single stats-augmented main query on ad list | Query Monitor shows JOINs on impressions/clicks tables    | Done   |
| Zero per-ad stat queries on render           | No `SELECT SUM(count) FROM advads_*` during column render | Done   |
| Sorting preserved                            | Sort by impressions, clicks, CTR works                    | Done   |
| Safe without tracking                        | No errors; accessors return `0`                           | Done   |
| Fresh stats each request                     | No stats written to persistent post object cache          | Done   |
| Works with `List_Filters`                    | Filters + stats on same page load                         | Done   |

---

## 3. Architecture (as implemented)

### 3.1 Tracking add-on — query JOIN

**File:** `advanced-ads-tracking/includes/admin/class-ad-list-table.php`

-   `posts_clauses` → `request_clauses()` adds aggregated impressions, clicks, CTR to the **main ad list query only**
-   `is_ad_list_main_query()` detects the screen via `get_current_screen()` with fallback to `$pagenow` / `$typenow` (because `get_current_screen()` is often unavailable during `posts_clauses`)
-   Safe CTR: `IF( imp.count > 0, cl.count / imp.count, 0 )`
-   Sortable column `orderby` mapping for `impressions`, `clicks`, `ctr`

### 3.2 Tracking add-on — request-scoped priming

**Same file:** `posts_results` @ priority 99 → `prime_stats_post_cache()`

After the JOIN query returns, copies stats from in-memory `WP_Post` objects into core's registry **before** WordPress reloads posts. Does **not** use `wp_cache_set` on the `posts` group (avoids stale stats in Redis/object cache).

### 3.3 Core — request-scoped registry

**File:** `includes/utilities/class-ad-list-stats.php`  
**Class:** `AdvancedAds\Utilities\Ad_List_Stats`

Static in-memory map for the current HTTP request only:

```php
Ad_List_Stats::set( $ad_id, [ 'clicks' => …, 'impressions' => …, 'ctr' => … ] );
Ad_List_Stats::get( $ad_id ); // null when not primed
```

### 3.4 Core — stat accessors

**File:** `includes/abstracts/abstract-ad.php`

`get_clicks()`, `get_impressions()`, `get_ctr()` read from `Ad_List_Stats::get()` and return **0** when not primed. No per-ad SQL in core; no dependency on tracking tables.

### 3.5 Tracking add-on — column UI

Unchanged: column registration, rendering, hidden columns, sortable labels.

---

## 4. Interaction with `List_Filters`

No required changes to `List_Filters` filter logic.

1. Main query runs with tracking JOIN → posts have stats in memory
2. `List_Filters::post_results` (priority 10) may filter/paginate; tracking `posts_results` (priority 99) primes `Ad_List_Stats` from the final post set
3. Column render → accessors read registry → no extra queries

`get_ad_summaries()` inside `List_Filters` may still prime plain post caches; that no longer affects stats because accessors do not read from post cache.

---

## 5. Edge cases

| Case                   | Behavior                                    |
| ---------------------- | ------------------------------------------- |
| Tracking not installed | `Ad_List_Stats` empty; accessors return `0` |
| Ad with 0 impressions  | `ctr = 0` from SQL `IF`                     |
| GA tracking method     | JOIN still runs; UI may hide columns        |
| Non-main queries       | JOIN and priming skipped                    |
| Page refresh           | Fresh JOIN + fresh registry                 |

---

## 6. Testing

### Unit tests (core)

-   `tests/Unit/Abstracts/AdTest.php` — registry read + zero fallback

### Manual (Query Monitor)

1. Open `wp-admin/edit.php?post_type=advanced_ads` with tracking active
2. Main query includes `advads_impressions` / `advads_clicks` JOINs
3. No per-ad `SUM(count)` on render
4. With `List_Filters` enabled: apply a filter; stats still correct

---

## 7. Out of scope

-   Joining stats on frontend ad queries
-   Cross-request caching of stats totals
-   Core `Stats_Query` class (considered; not implemented — tracking owns the JOIN)

---

## 8. Commits

-   **advanced-ads:** `fix(ads): read list stats from request-scoped registry`
-   **advanced-ads-tracking:** `perf(admin): fix ad list stats JOIN and request-scoped priming`
