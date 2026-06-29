# Repository and entity caching — design spec

**Date:** 2026-06-16  
**Status:** Implemented (Phase 1 complete)  
**Scope:** Cross-request list caching, request-scoped hydration, CRUD invalidation, and related abstracts-layer performance fixes.

---

## 1. Problem statement

Advanced Ads repositories repeatedly query all ads, groups, and placements on admin loads and during hydration loops. Without caching:

-   Dropdown/list methods hit the database on every request.
-   Hydrating many entities causes N+1 post/meta (or term/meta) reads.
-   Factories re-build the same entity objects multiple times per request.

The goal is **measurable reduction in list queries and hydration cost** without persisting full PHP entity objects in Redis, and without adding fragile per-entity cross-request caches.

---

## 2. Success criteria

| Criterion                         | Verification                                                                               |
| --------------------------------- | ------------------------------------------------------------------------------------------ |
| List queries cached cross-request | `get_*_summaries()`, derived dropdown/published IDs read from `wp_cache` after first build |
| Hydration batch-primed            | `_prime_post_caches()` for ads/placements; term meta priming for groups                    |
| Request memoization               | Same ID via factory returns same instance within one request                               |
| CRUD safety                       | Create/update/delete and WP hooks bump cache version; factory instance cache cleared       |
| No stale wrapper/layout           | Ad wrapper cache invalidated when layout props change                                      |
| Tests                             | `tests/Unit/Utilities/` (Cache, list cache, invalidator) and `tests/Unit/Abstracts/`       |

---

## 3. Architecture

### 3.1 Cache helper (`AdvancedAds\Utilities\Cache`)

-   **Group:** `advanced-ads` (single `wp_cache` group for all entries).
-   **Versioned keys:** `{prefix}:v{N}:{logical_key}` — bumping `{prefix}:version` invalidates an entire namespace without deleting individual keys (Redis-friendly).
-   **Prefixes:** `ads`, `groups`, `placements`.
-   **Logical keys:**
    -   `summaries` — ID => lightweight list row per entity; dropdown and published IDs are derived in PHP.
    -   `all_ids` — reserved for future use if needed.
-   **API:** explicit `get()` / `set()` / `delete()` / `flush_group()` / `flush_all()` — no callback wrapper.

### 3.2 Repository list cache

| Repository             | Cached methods                                                                                                                 | Notes                                                                    |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------ |
| `Ad_Repository`        | `get_ad_summaries()`, `get_ads_dropdown()`, `get_all_ads()`                                                                    | Summaries cached; dropdown is a title pluck; hydration via batch priming |
| `Group_Repository`     | `get_group_summaries()`, `get_groups_dropdown()`, `get_all_groups()`                                                           | Summaries cached; term meta priming on build                             |
| `Placement_Repository` | `get_placement_summaries()`, `get_placements_dropdown()`, `get_published_ids()`, `get_all_placements()`, `get_all_published()` | Summaries cached; published IDs filtered from status                     |

In-method `static` caches on list methods were removed.

### 3.3 Batch hydration (C5)

-   **Ads / placements:** `_prime_post_caches( $ids, false, true )` before hydration loops.
-   **Groups:** `wp_update_term_cache()` + `update_termmeta_cache()`.

Priming reduces DB round-trips after IDs are known; it does not replace list caching.

### 3.4 Factory instance cache (C6)

-   `Abstract\Factory` holds `$instances` keyed per entity ID (and type override where applicable).
-   `clear_instance_cache()` called from `Cache_Invalidator` on writes.
-   Request-scoped only — not persisted to Redis.

### 3.5 Invalidation (`AdvancedAds\Cache_Invalidator`)

Registered in `includes/class-plugin.php`.

**Repository CRUD:** each `create` / `update` / `delete` calls the matching `invalidate_*()` method.

**WordPress hooks (safety net):**

-   `save_post`, `deleted_post`, `trashed_post`, `untrashed_post` — ad and placement post types.
-   `created_term`, `edited_term`, `delete_term` — group taxonomy.
-   `advanced-ads-import` — `invalidate_all()`.

**Not invalidated on ad–group relation changes alone** — membership updates do not change list membership; avoid unnecessary full list flushes.

Each `invalidate_*()`:

1. `Cache::flush_group( $prefix )` — version bump.
2. Factory `clear_instance_cache()` for that entity type.

---

## 4. Implemented phases

| ID  | Description                          | Status |
| --- | ------------------------------------ | ------ |
| C1  | `Cache` helper + versioned keys      | Done   |
| C2  | Ad list/dropdown cache               | Done   |
| C3  | Group list/dropdown cache            | Done   |
| C4  | Placement list + published IDs cache | Done   |
| C5  | Batch hydration / priming            | Done   |
| C6  | Factory request instance cache       | Done   |
| C7  | CRUD + hook invalidation             | Done   |

---

## 5. Explicitly dropped

### C8 — Per-entity serializable cache (`ads:vN:entity:{id}`)

**Decision:** Dropped after review.

**Rationale:** Marginal win over Phase 1. WordPress object cache already holds post/meta rows; batch priming (C5) and factory memoization (C6) cover the practical hydration path. Per-entity cache adds invalidation surface for little gain on typical installs.

### Abstracts micro-caches (#6, #7, #10–#12)

Instance caches for `get_clicks()` / `get_impressions()`, `get_data()`, `get_types( false )`, and `get_visitor_conditions()` were evaluated and **not shipped** — duplicate calls per instance are rare; `array_merge` / small `array_filter` cost is negligible.

---

## 6. Abstracts-layer fixes (shipped with cache work)

| Area                           | Change                                                                             | Status                      |
| ------------------------------ | ---------------------------------------------------------------------------------- | --------------------------- |
| `Group::get_ad_weights()`      | Instance cache + invalidation on `set_ad_weights()`; clears `$ads` / `$sorted_ads` | Done — meaningful with WPML |
| `Group::shuffle_ads()`         | O(n) weight total tracking (was O(n²) via repeated `array_sum`)                    | Done                        |
| `Ad::get_wrapper_attributes()` | Invalidate `$wrapper` when layout props change                                     | Done — correctness          |
| `Data::set_object_read()`      | `(bool)` cast instead of `boolval()`                                               | Done                        |
| `get_allowed_groups()` (#4)    | Addressed by C2–C6; no placement-type ad index                                     | Done                        |
| `Data::__toString()` (#11)     | No code change; avoid `(string) $entity` in hot paths                              | Guidance only               |

Bug fixes #1–#3 (`unset_prop`, visitor foreach, `get_data_keys`) were completed earlier with tests under `tests/Unit/Abstracts/`.

---

## 7. Testing

```bash
composer test-install   # once
composer test -- tests/Unit/Utilities/
composer test -- tests/Unit/Abstracts/
```

**Meaningful tests (not cache-reference trivia):**

-   Cache get/set/flush/version bump — `CacheTest.php`
-   List cache + invalidation on CRUD — `RepositoryListCacheTest.php`, `CacheInvalidatorTest.php`
-   `set_ad_weights` refreshes attached ads — `GroupTest.php`
-   Wrapper reflects position after layout change — `AdTest.php`
-   `shuffle_ads` returns all weighted ads — `GroupTest.php`

---

## 8. Out of scope

-   Persisting full `Ad` / `Group` / `Placement` objects in Redis.
-   Placement-type-specific ad index for `get_allowed_groups()`.
-   Cross-request per-entity props cache (C8).
-   Admin stats table query deduplication (`get_clicks` / `get_impressions`).

---

## 9. Key files

| File                                                 | Role                           |
| ---------------------------------------------------- | ------------------------------ |
| `includes/utilities/class-cache.php`                 | Versioned `wp_cache` wrapper   |
| `includes/class-cache-invalidator.php`               | CRUD + WP hook invalidation    |
| `includes/ads/class-ad-repository.php`               | Ad list cache + priming        |
| `includes/groups/class-group-repository.php`         | Group list cache + priming     |
| `includes/placements/class-placement-repository.php` | Placement list + published IDs |
| `includes/abstracts/abstract-factory.php`            | Request instance memoization   |
| `tests/Unit/Utilities/CacheTest.php`                 | Cache helper tests             |
| `tests/Unit/Utilities/RepositoryListCacheTest.php`   | Repository list cache tests    |
| `tests/Unit/Utilities/CacheInvalidatorTest.php`      | Invalidation tests             |
