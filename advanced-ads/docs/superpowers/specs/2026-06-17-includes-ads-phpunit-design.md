# `includes/ads` — PHPUnit coverage and targeted fixes

**Date:** 2026-06-17  
**Status:** Complete  
**Scope:** PHPUnit unit tests for `includes/ads`, plus small production fixes that make those tests meaningful. No Playwright work in this pass.

---

## 1. Problem statement

The `includes/ads` layer (repository, factory, types, concrete models, group relation) has partial PHPUnit coverage. Several code-review findings are either untested stubs, dead code, or avoidable per-read CPU work. We want **incremental, reviewable PR-sized steps** — one TODO at a time.

Phase 1 list-cache and factory memoization is already shipped ([2026-06-16 spec](./2026-06-16-repository-and-entity-cache-design.md)). This spec builds on that without adding new cross-request cache keys.

---

## 2. Success criteria

| Criterion                                  | Verification                                                                            |
| ------------------------------------------ | --------------------------------------------------------------------------------------- |
| Dead factory return removed                | `composer test -- tests/Unit/Ads/FactoryTest.php`                                       |
| `get_ads_by_type()` implemented and tested | `RepositoryTest` type-filter tests                                                      |
| Legacy meta migration skipped when safe    | `RepositoryTest` migration tests                                                        |
| Group relation weight sync tested          | `AdGroupRelationTest`                                                                   |
| GAM/AMP types registered in tests          | Extended `TypesTest`                                                                    |
| No regressions                             | `composer test -- tests/Unit/Ads/` + `tests/Unit/Utilities/RepositoryListCacheTest.php` |

---

## 3. Production fixes (approved)

### 3.1 `Ad_Factory::get_ad()` — remove dead return

Delete unreachable `return new Ad_Content();` after the `catch` block in `includes/ads/class-ad-factory.php`.

### 3.2 `Ad_Repository::get_ads_by_type()` — implement stub

Query ad post IDs by serialized meta fragment, then hydrate with existing batch priming:

```php
public function get_ads_by_type( string $type ): array {
    $type = sanitize_key( $type );
    if ( '' === $type ) {
        return [];
    }

    $query = $this->query(
        [
            'fields'     => 'ids',
            'meta_query' => [
                [
                    'key'     => self::OPTION_METAKEY,
                    'value'   => sprintf( ':"%s";', $type ),
                    'compare' => 'LIKE',
                ],
            ],
        ],
        true
    );

    return $this->hydrate_ads( $query->posts );
}
```

**Note:** `LIKE` on serialized meta is a standard WordPress pattern here; no callers exist yet, so false-positive risk is acceptable for v1.

### 3.3 `migrate_values()` — skip when already migrated

Add `needs_meta_migration( array $values ): bool` and early-return from `migrate_values()` when legacy keys are absent:

-   `output` or `visitor` keys present, **or**
-   `position` is one of `left`, `center`, `right`

Still normalize `margin` integers when margin keys exist on the fast path if needed.

### 3.4 `Ad_Content::prepare_frontend_output()` — remove dead branch

Remove duplicate/unreachable `wp_filter_content_tags` check (lines 52–55 area). Behavior unchanged for supported WP versions.

---

## 4. PHPUnit tests (approved)

### 4.1 New: `tests/Unit/Ads/RepositoryTest.php`

| Test                                      | Behavior under test                                          |
| ----------------------------------------- | ------------------------------------------------------------ |
| `test_read_throws_for_invalid_ad`         | Invalid ID / wrong post type throws                          |
| `test_migrate_legacy_position_on_read`    | Legacy `{ position: left, clearfix: true }` → `left_nofloat` |
| `test_skips_migration_for_modern_meta`    | Modern meta without legacy keys unchanged                    |
| `test_get_ads_by_type_filters_correctly`  | Plain vs content ads filtered                                |
| `test_get_ads_by_type_empty_for_unknown`  | Unknown type → `[]`                                          |
| `test_get_ads_by_group_id`                | Ad in group via taxonomy                                     |
| `test_get_ads_by_placement_id_with_ad`    | Placement item is ad                                         |
| `test_get_ads_by_placement_id_with_group` | Placement item is group                                      |

### 4.2 New: `tests/Unit/Ads/AdGroupRelationTest.php`

| Test                                          | Behavior under test             |
| --------------------------------------------- | ------------------------------- |
| `test_adding_ad_to_group_sets_default_weight` | Default weight `10` on term add |
| `test_removing_ad_from_group_clears_weight`   | Weight removed on term remove   |
| `test_relate_updates_ad_meta_group_ids`       | `AD_META_GROUP_IDS` synced      |

### 4.3 Extend: `tests/Unit/Ads/TypesTest.php`

Assert `gam` and `amp` types register (same pattern as `content` / `plain`).

---

## 5. Incremental TODO checklist

Work **one TODO per session/PR**. Each item lists files, action, and how to verify. Mark `[x]` when done.

### In scope — do in order

| ID          | Task                                                                             | Files                                    | Verify                                                                                                     |
| ----------- | -------------------------------------------------------------------------------- | ---------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| **TODO-01** | ~~Remove dead `return new Ad_Content()` in factory~~ **Done**                    | `includes/ads/class-ad-factory.php`      | `composer test -- tests/Unit/Ads/FactoryTest.php`                                                          |
| **TODO-02** | ~~Implement `get_ads_by_type()`~~ **Done**                                       | `includes/ads/class-ad-repository.php`   | `composer test -- tests/Unit/Ads/RepositoryTest.php --filter get_ads_by_type`                              |
| **TODO-03** | ~~Add `get_ads_by_type` tests~~ **Done**                                         | `tests/Unit/Ads/RepositoryTest.php`      | `composer test -- tests/Unit/Ads/RepositoryTest.php --filter get_ads_by_type`                              |
| **TODO-04** | ~~Add `needs_meta_migration()` + early exit in `migrate_values()`~~ **Done**     | `includes/ads/class-ad-repository.php`   | `composer test -- tests/Unit/Ads/RepositoryTest.php --filter migrate`                                      |
| **TODO-05** | ~~Add migration read tests~~ **Done**                                            | `tests/Unit/Ads/RepositoryTest.php`      | `composer test -- tests/Unit/Ads/RepositoryTest.php --filter migrate\|read_throws`                         |
| **TODO-06** | ~~Add finder tests (`get_ads_by_group_id`, `get_ads_by_placement_id`)~~ **Done** | `tests/Unit/Ads/RepositoryTest.php`      | `composer test -- tests/Unit/Ads/RepositoryTest.php`                                                       |
| **TODO-07** | ~~Add `AdGroupRelationTest` (all three cases)~~ **Done**                         | `tests/Unit/Ads/AdGroupRelationTest.php` | `composer test -- tests/Unit/Ads/AdGroupRelationTest.php`                                                  |
| **TODO-08** | ~~Fix `Ad_Content` dead branch~~ **Done**                                        | `includes/ads/class-ad-content.php`      | `composer test -- tests/Unit/Ads/Concrete/ContentAdTest.php`                                               |
| **TODO-09** | ~~Extend `TypesTest` for GAM + AMP~~ **Done**                                    | `tests/Unit/Ads/TypesTest.php`           | `composer test -- tests/Unit/Ads/TypesTest.php`                                                            |
| **TODO-10** | ~~Full regression pass for ads layer~~ **Done**                                  | —                                        | `composer test -- tests/Unit/Ads/` and `composer test -- tests/Unit/Utilities/RepositoryListCacheTest.php` |

### Code-review backlog — closed

| ID           | Issue                                                                                | Resolution                                                                                                 |
| ------------ | ------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------- |
| **TODO-B01** | ~~`get_all_ads()` loads full objects for every ID — memory on large sites~~ **Done** | All repositories cache `KEY_SUMMARIES`; dropdown/published IDs derived via `wp_list_pluck` / status filter |
| **TODO-B02** | ~~Factory `get_ad_type()` + `read()` double meta read~~ **Dropped**                  | No measurable optimization impact; WP object cache dedupes meta reads after first load                     |
| **TODO-B03** | ~~`Ad_Group_Relation` instantiated on every save~~ **Dropped**                       | No measurable optimization impact; leave as-is                                                             |
| **TODO-B04** | ~~Cached type-index for `get_ads_by_type()`~~ **Dropped**                            | No callers yet; no optimization benefit until usage exists                                                 |
| **TODO-B05** | ~~Playwright: edit/delete ad, image ad E2E~~ **Dropped**                             | Out of scope for this PHPUnit pass; pursue separately if E2E coverage is needed                            |

---

## 6. PHPUnit coverage audit (`includes/ads`)

**Suite:** `composer test -- tests/Unit/Ads/` — **83 tests** (passing).

| Production file                  | Test coverage                                                                               |
| -------------------------------- | ------------------------------------------------------------------------------------------- |
| `class-ads.php`                  | `BootstrapTest` — `initialize()` wires factory, repository, types                           |
| `class-ad-factory.php`           | `FactoryTest` — create, get, memoization, filter override, input shapes                     |
| `class-ad-repository.php`        | `RepositoryTest` — CRUD, migration, summaries/dropdown, finders, `get_ads_by_type`, `query` |
| `class-ad-types.php` + `types/*` | `TypesTest` — registration, classnames, premium flags, unknown type                         |
| `class-ad-group-relation.php`    | `AdGroupRelationTest` — weights on add/remove, meta sync                                    |
| `class-ad-plain.php`             | `PlainAdTest` — output, position, wrapper, margin, shortcode/PHP gates                      |
| `class-ad-content.php`           | `ContentAdTest` — output, shortcodes                                                        |
| `class-ad-image.php`             | `ImageAdTest` — attachment output, URL wrap                                                 |
| `class-ad-dummy.php`             | `DummyAdTest` — placeholder output, URL, position                                           |
| `class-ad-group.php`             | `GroupAdTest` — empty output, group_id props                                                |

**Related cache tests:** `tests/Unit/Utilities/RepositoryListCacheTest.php`, `CacheInvalidatorTest.php` — summaries cache for ads/groups/placements.

**Acceptable gaps (no action):**

-   `types/type-gam.php`, `types/type-amp.php` — metadata-only; concrete class is `Ad_Dummy`; covered by `TypesTest`.
-   `Ad_Group::prepare_frontend_output()` with a live group render — defers to `get_the_group()` (frontend integration); empty path covered.
-   `functions-ad.php` thin wrappers — exercised indirectly via repository/factory tests.

---

## 7. Suggested session flow

For each **TODO-01 … TODO-10**:

1. Implement only that item’s code/test changes.
2. Run the listed verify command.
3. Review diff (should stay small — typically 1–2 files).
4. Commit when ready (optional per item or batch a few related TODOs).

**Pairing order:** TODO-02 before TODO-03 (implementation before tests) **or** TODO-03 first with failing tests if you prefer TDD — either works; default is fix then test for TODO-02/03.

---

## 8. Key files

| File                                       | Role                                        |
| ------------------------------------------ | ------------------------------------------- |
| `includes/ads/class-ad-repository.php`     | CRUD, queries, migration, `get_ads_by_type` |
| `includes/ads/class-ad-factory.php`        | Instance cache, type resolution             |
| `includes/ads/class-ad-group-relation.php` | Group weight sync on save                   |
| `includes/ads/class-ad-content.php`        | Rich content output                         |
| `includes/ads/class-ad-types.php`          | Type registration                           |
| `tests/Unit/Ads/RepositoryTest.php`        | New repository tests                        |
| `tests/Unit/Ads/AdGroupRelationTest.php`   | New relation tests                          |
| `tests/Unit/Ads/TypesTest.php`             | Extended type registration tests            |

---

## 9. Out of scope

-   Playwright / acceptance tests (see TODO-B05 — dropped)
-   New cross-request cache keys beyond `KEY_SUMMARIES`
-   Micro-optimizations with no measurable impact (see TODO-B02–B04 — dropped)
-   Lazy hydration / API changes to `get_all_ads()` — partial: summaries API added; `get_all_ads()` retained for full-object callers (e.g. upgrades)
-   Refactoring `abstract-ad.php` beyond what tests require
