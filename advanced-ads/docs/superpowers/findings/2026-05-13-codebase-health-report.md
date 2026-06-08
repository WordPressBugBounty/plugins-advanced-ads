# Codebase health report

**Last updated:** 2026-05-13  
**Git / reproducibility:** PHPUnit and metrics were captured on **2026-05-13**. For the exact commit, run `git rev-parse HEAD` after checkout, or `git log -1 --format=%H -- docs/superpowers/artifacts/php-file-metrics.tsv` for the metrics artifact anchor.  
**Artifacts:** `docs/superpowers/artifacts/php-file-metrics.tsv`, `flow-test-matrix.md`, `security-checklist-results.md`, `duplication-notes.md`

---

## Executive summary

-   **Scale:** **698** tracked PHP files (excluding `vendor/`, `node_modules/`, `packages/composer/`, and `packages/*/vendor/`) appear in `php-file-metrics.tsv`, sorted by commits in the last **24 months**.
-   **Churn concentration:** Core domain lives under `includes/` (abstracts, repositories, admin AJAX, REST) plus `public/class-advanced-ads.php` and root `advanced-ads.php`. Highest churn: `includes/abstracts/abstract-ad.php` (**54** commits / 24m), `includes/class-plugin.php` (**48**), `advanced-ads.php` (**30**).
-   **PHPUnit:** **145** tests, **251** assertions — **OK** on PHP **8.5.5** with local WP test install (`composer test`).
-   **Playwright:** **Blocked** in local agent sandbox (Chromium binary missing: run `npx playwright install`). **CI:** `.github/workflows/playwright.yml` uses `wp-env` + `npx playwright install --with-deps chromium` — treat CI as source of truth for E2E green.
-   **Regression priority:** Strengthen **frontend / public rendering** coverage; `tests/Acceptance/` currently lists **admin** specs only — see [flow-test-matrix.md](../artifacts/flow-test-matrix.md).
-   **Security:** AJAX surface is large (`includes/admin/class-ajax.php`); sample review shows good patterns in places (nonce + capability) but **nopriv** handlers and full handler audit remain open — see [security-checklist-results.md](../artifacts/security-checklist-results.md).
-   **Duplication:** Structural parallel “abstract entity” family drives maintenance cost — see [duplication-notes.md](../artifacts/duplication-notes.md).

---

## A — Architecture and heat map

### Subsystems

| Subsystem                          | Role                              | Representative paths                                                                                                                              |
| ---------------------------------- | --------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| Bootstrap                          | Constants, loader, hooks          | `advanced-ads.php`, `includes/class-plugin.php`, `includes/class-constants.php`                                                                   |
| Domain — Ads / Groups / Placements | Entities, repositories, factories | `includes/abstracts/abstract-ad.php`, `abstract-group.php`, `abstract-placement.php`, `includes/ads/`, `includes/groups/`, `includes/placements/` |
| Admin UI                           | Menus, pages, metabox AJAX        | `includes/admin/`, `admin/includes/`                                                                                                              |
| Public output                      | Front rendering, scripts          | `public/class-advanced-ads.php`, `includes/frontend/`                                                                                             |
| REST                               | Block editor / quick actions      | `includes/rest/`                                                                                                                                  |
| Integrations                       | Adsense, Gutenberg, importers     | `modules/`, `includes/importers/`                                                                                                                 |
| Legacy / compat                    | Older class layout                | `classes/` (e.g. `display-conditions.php`, `frontend_checks.php`)                                                                                 |
| Assets                             | Built JS/CSS fingerprints         | `assets/dist/*.asset.php` (high commit count, low line count — build noise)                                                                       |

### Hotspots (from metrics)

Top **10** by `commits_24m` (see TSV for full sort):

1. `includes/abstracts/abstract-ad.php`
2. `includes/class-plugin.php`
3. `advanced-ads.php`
4. `includes/ads/class-ad-repository.php`
5. `includes/class-assets-registry.php`
6. `public/class-advanced-ads.php`
7. `includes/abstracts/abstract-group.php`
8. `includes/admin/class-assets.php`
9. `includes/abstracts/abstract-placement.php`
10. `includes/utilities/class-wordpress.php`

**Graphify:** Use `graphify-out/GRAPH_REPORT.md` for dependency hints; communities mix product and dev tooling — validate in source.

---

## B — Inventory and automation

### Methodology

-   **Churn + metadata:** `php bin/export-php-metrics.php` writes `docs/superpowers/artifacts/php-file-metrics.tsv` (columns: `path`, `lines`, `last_commit_iso`, `commits_24m`).
-   **Exclusions:** `vendor/`, `node_modules/`, `packages/composer/` (generated), `packages/*/vendor/`.
-   **Sort:** Data rows sorted by `commits_24m` descending.

### Metrics summary

| Metric                            | Value            |
| --------------------------------- | ---------------- |
| PHP files in TSV                  | 698              |
| Single-pass 24m churn aggregation | Yes (see script) |
| PHPUnit (`composer test`)         | 145 tests — pass |

### Deep dives (worst N%)

-   Default shortlist: **top 50** rows in `php-file-metrics.tsv` after header.
-   **Deep-dive priority:** `includes/admin/class-ajax.php` (AJAX + security), `includes/abstracts/*.php` (domain changes), `includes/importers/class-xml-importer.php` / `class-ad-inserter.php` (serialization and trust boundaries).

---

## C — Security and compliance

Summary and evidence: **[security-checklist-results.md](../artifacts/security-checklist-results.md)**

Highlights:

-   **AJAX:** Central registry in `includes/admin/class-ajax.php`; verify nonce + capability on every handler; review **nopriv** endpoints.
-   **REST:** Multiple `register_rest_route` call sites — complete `permission_callback` inventory in a follow-up task.
-   **Serialization:** Prefer `WordPress::maybe_unserialize`; scrutinize `includes/importers/class-ad-inserter.php` and remote updater bodies.

---

## Regression confidence

-   **Matrix:** **[flow-test-matrix.md](../artifacts/flow-test-matrix.md)**
-   **PHPUnit:** Keep as **required** merge gate (already in `.github/workflows/php-unit.yml` on `pull_request` / `push` to `develop`).
-   **Playwright:** Run via `npm run test:playwright` with `wp-env` per `.github/workflows/playwright.yml`; local dev should mirror CI (`npx playwright install`, WP on **8888** per `playwright.config.ts`).

### Baseline run (2026-05-13)

| Suite      | Command                   | Result                                                                                           |
| ---------- | ------------------------- | ------------------------------------------------------------------------------------------------ |
| PHPUnit    | `composer test`           | **PASS** — 145 tests, 251 assertions, PHPUnit 9.6.34                                             |
| Playwright | `npm run test:playwright` | **BLOCKED locally** — Chromium executable missing in sandbox; use CI or `npx playwright install` |

---

## Recommended roadmap

| Order | Theme                      | Accountable owner               | Definition of done                                                                                                                                                                     |
| ----- | -------------------------- | ------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1     | **Regression backbone**    | Plugin lead                     | Every critical flow in [flow-test-matrix.md](../artifacts/flow-test-matrix.md) has PHPUnit and/or Playwright or explicit manual release step; frontend rendering has at least one E2E. |
| 2     | **Security surfaces**      | Plugin lead + security reviewer | AJAX handler audit + REST `permission_callback` table; no open **High** items from checklist without ticket.                                                                           |
| 3     | **Boundary stabilization** | Engineering manager             | Hotspot files have named owners; refactors behind tests.                                                                                                                               |
| 4     | **Dedup / extract**        | Plugin lead                     | Second identical change triggers shared abstraction (per duplication notes).                                                                                                           |
| 5     | **CI guardrails**          | Plugin lead                     | Optional: exclude `assets/dist/*.asset.php` from churn reports; optional clone-detection job.                                                                                          |

---

## CI recommendations

Existing workflows (no change required for this report):

-   **PHPUnit:** `.github/workflows/php-unit.yml` — matrix PHP 7.4 / 8.0 / 8.2, MySQL service, `composer test`.
-   **Playwright:** `.github/workflows/playwright.yml` — `npm ci`, global `@wordpress/env`, `wp-env start`, `npx playwright install --with-deps chromium`, then `wp-scripts test-playwright` (see workflow tail for exact test command).

**Recommendations:**

1. Keep **PHPUnit on every PR** to `develop` / mainline branches.
2. Keep **Playwright on PR**; if duration grows, restrict to `paths` filters for `tests/Acceptance/`, `src/`, `includes/`, `admin/`, `package-lock.json`.
3. Document for contributors: local E2E requires `npx playwright install` once per machine and a running site on `baseURL` (**8888** in config, **8889** fallback noted by wp-scripts when `@wordpress/env` missing).

---

## Duplication and structural debt

See **[duplication-notes.md](../artifacts/duplication-notes.md)**.
