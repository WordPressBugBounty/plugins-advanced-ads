# Codebase health audit and regression confidence — implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Execute the approved design in `docs/superpowers/specs/2026-05-13-codebase-health-and-regression-confidence-design.md`: produce evidence-backed findings (A+B+C), machine-readable metrics, and a regression coverage map aligned with existing PHPUnit and Playwright (`wp-scripts`).

**Architecture:** Work in waves—**B** (inventory and signals) → **A** (subsystem heat map) → **C** (security checklist)—while maintaining a living **findings** document and a **flow-to-test** matrix that ties product risk to `tests/Unit/` and `tests/Acceptance/`.

**Tech Stack:** Git, PHP 8.x, Composer (`phpunit/phpunit`), `@wordpress/scripts` (Playwright), existing `npm run lint:php`, optional `graphify query` / `graphify-out/GRAPH_REPORT.md` for navigation hints.

---

## File map (outputs)

| Path                                                             | Responsibility                                                                                        |
| ---------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `docs/superpowers/findings/2026-05-13-codebase-health-report.md` | Master narrative: sections A, B, C + regression appendix (update date in filename if work spans days) |
| `docs/superpowers/artifacts/php-file-metrics.tsv`                | Machine-readable per-file metrics (see Task 2 column spec)                                            |
| `docs/superpowers/artifacts/flow-test-matrix.md`                 | Critical flows × PHPUnit / Playwright coverage and gaps                                               |
| `docs/superpowers/artifacts/security-checklist-results.md`       | Checklist pass log for Wave C                                                                         |

---

### Task 1: Create output directories and report skeleton

**Files:**

-   Create: `docs/superpowers/findings/2026-05-13-codebase-health-report.md`
-   Create: `docs/superpowers/artifacts/.gitkeep` (optional; only if empty dirs must exist in git—otherwise skip and let Task 2 create files)

**Content skeleton for `2026-05-13-codebase-health-report.md`:**

```markdown
# Codebase health report

## Executive summary

<!-- 3–8 bullets after analysis -->

## A — Architecture and heat map

### Subsystems

<!-- Bootstrap, admin, public, placements, upgrades, REST/AJAX, assets -->

### Hotspots (from metrics)

<!-- Filled in Task 4 -->

## B — Inventory and automation

### Methodology

### Metrics summary

### Deep dives (worst N%)

<!-- Link rows in php-file-metrics.tsv -->

## C — Security and compliance

<!-- Link security-checklist-results.md -->

## Regression confidence

<!-- Link flow-test-matrix.md -->

## Recommended roadmap

<!-- Themes from design spec §6 -->
```

-   [x] **Step 1:** Add the skeleton file at `docs/superpowers/findings/2026-05-13-codebase-health-report.md` with the structure above (placeholders OK in this task only).

-   [x] **Step 2:** Commit.

```bash
git add docs/superpowers/findings/2026-05-13-codebase-health-report.md
git commit -m "docs: add codebase health report skeleton"
```

Expected: commit succeeds; `develop` (or current branch) advances by one commit.

---

### Task 2: Generate `php-file-metrics.tsv` (Wave B — inventory)

**Files:**

-   Create: `docs/superpowers/artifacts/php-file-metrics.tsv`

**Column spec (header row, tab-separated):**

`path	lines	last_commit_iso	commits_24m`

-   `path`: repo-relative path to `.php` under plugin root (exclude `vendor/`, `node_modules/`, `packages/` if present as dependency trees).
-   `lines`: `wc -l` count.
-   `commits_24m`: number of commits touching that file in last 24 months (`git log --follow --oneline --since=... -- <path> | wc -l`).

-   [x] **Step 1:** From repository root, generate TSV (excludes `vendor/` and `node_modules/` paths by prefix skip):

```bash
cd "$(git rev-parse --show-toplevel)"
mkdir -p docs/superpowers/artifacts
{
  printf '%s\t%s\t%s\t%s\n' path lines last_commit_iso commits_24m
  git ls-files '*.php' | while IFS= read -r f; do
    case "$f" in vendor/*|node_modules/*|packages/*/vendor/*) continue ;; esac
    lines=$(wc -l < "$f" 2>/dev/null | tr -d ' ' || echo 0)
    last=$(git log -1 --format=%cI -- "$f" 2>/dev/null || echo "")
    commits=$(git log --since="24 months ago" --oneline -- "$f" 2>/dev/null | wc -l | tr -d ' ')
    printf '%s\t%s\t%s\t%s\n' "$f" "$lines" "$last" "$commits"
  done
} > docs/superpowers/artifacts/php-file-metrics.tsv
```

-   [x] **Step 2:** Sort TSV by `commits_24m` descending (excluding header) and keep full file for appendix; in the report, reference **top 50** as default “worst N%” shortlist unless file count is tiny.

-   [x] **Step 3:** Commit artifact + any small script under `bin/` if you add one (e.g. `bin/export-php-metrics.sh`).

```bash
git add docs/superpowers/artifacts/php-file-metrics.tsv bin/export-php-metrics.sh 2>/dev/null || true
git commit -m "docs: add PHP file metrics artifact for health audit"
```

---

### Task 3: Baseline tests — record green state

**Files:**

-   Modify: `docs/superpowers/findings/2026-05-13-codebase-health-report.md` (add subsection under **B** or **Regression** with command outputs / dates)

-   [x] **Step 1:** Install WP test DB if not already (one-time per machine).

```bash
composer test-install
```

Expected: script completes per `composer.json` / `bin/install-wp-tests.sh` documentation; MySQL reachable.

-   [x] **Step 2:** Run PHPUnit.

```bash
composer test
```

Expected: exit code `0`; note PHPUnit version and PHP version in the report.

-   [x] **Step 3:** Run Playwright via wp-scripts (requires local WP at `playwright.config.ts` baseURL, typically `http://localhost:8888`).

```bash
npm run test:playwright
```

Expected: exit code `0` when environment is up; if environment missing, document **blocked** with exact error in findings (do not fake pass).

-   [x] **Step 4:** Paste summarized results (pass/fail, date, commit SHA `git rev-parse HEAD`) into the findings doc.

-   [x] **Step 5:** Commit findings updates.

```bash
git add docs/superpowers/findings/2026-05-13-codebase-health-report.md
git commit -m "docs: record baseline PHPUnit and Playwright results"
```

---

### Task 4: Subsystem heat map (Wave A)

**Files:**

-   Modify: `docs/superpowers/findings/2026-05-13-codebase-health-report.md`

-   [x] **Step 1:** Build a markdown table **Subsystem | Description | Representative paths | Top churn files (from TSV)** for at least: `advanced-ads.php` + bootstrap, `admin/`, `classes/`, `includes/`, `public/`, `modules/`, `src/`, `views/`, `upgrades/`.

-   [x] **Step 2:** Cross-check with `graphify-out/GRAPH_REPORT.md` “Surprising connections” / communities for **hints** only; add a short note that graph includes dev tooling and must not be read as product-only.

-   [x] **Step 3:** Commit.

```bash
git add docs/superpowers/findings/2026-05-13-codebase-health-report.md
git commit -m "docs: add subsystem heat map to health report"
```

---

### Task 5: Flow–test matrix (regression backbone)

**Files:**

-   Create: `docs/superpowers/artifacts/flow-test-matrix.md`
-   Modify: `docs/superpowers/findings/2026-05-13-codebase-health-report.md` (link matrix; summarize gaps)

**Matrix columns:** `Flow | User-visible outcome | PHPUnit class/file | Playwright spec | Gap action`

**Seed rows (fill “Gap action” after review):**

| Flow               | Outcome         | PHPUnit                                                                    | Playwright                                          |
| ------------------ | --------------- | -------------------------------------------------------------------------- | --------------------------------------------------- |
| Ads admin listing  | List renders    | (scan `tests/Unit/Ads/`)                                                   | `tests/Acceptance/Admin/Ads/listing.spec.ts`        |
| New ad             | Create flow     | `tests/Acceptance/Admin/Ads/new.ad.spec.ts` (E2E only—note gap if no unit) | same                                                |
| Ad label           | Label display   | `tests/Unit/Shortcodes/TheAdShortcodeSecurityTest.php` (if related)        | `tests/Acceptance/Admin/Ads/ad.label.spec.ts`       |
| Groups listing     | List renders    | `tests/Unit/Groups/`                                                       | `tests/Acceptance/Admin/Groups/listing.spec.ts`     |
| Placements listing | List renders    | `tests/Unit/Placements/`                                                   | `tests/Acceptance/Admin/Placements/listing.spec.ts` |
| Admin home         | Dashboard loads | —                                                                          | `tests/Acceptance/Admin/homepage.spec.ts`           |

-   [x] **Step 1:** Enumerate every file under `tests/Unit/` and `tests/Acceptance/**/*.spec.ts`; map each to at least one flow or mark as **technical/util**.

-   [x] **Step 2:** Identify **critical uncovered** flows (upgrade, license, importer `tests/Unit/Importers/ApiTest.php`, in-content injector tests, etc.) and set **Gap action** to: add PHPUnit | add Playwright | document manual release check.

-   [x] **Step 3:** Commit.

```bash
git add docs/superpowers/artifacts/flow-test-matrix.md docs/superpowers/findings/2026-05-13-codebase-health-report.md
git commit -m "docs: add flow-to-test regression matrix"
```

---

### Task 6: Security checklist (Wave C)

**Files:**

-   Create: `docs/superpowers/artifacts/security-checklist-results.md`
-   Modify: `docs/superpowers/findings/2026-05-13-codebase-health-report.md` (summary + link)

**Checklist rows (one table):** Item | Scope (how verified) | Pass/Fail/NA | Notes | File:line examples

Minimum items:

1. AJAX actions: `check_ajax_referer` / capability checks on handlers under `admin/`, `includes/`, `classes/`.
2. `$_GET` / `$_POST` / `$_REQUEST` in admin: sanitized and authorized.
3. SQL: `$wpdb->prepare` for dynamic SQL in hot files from Task 2 top list.
4. Output: `esc_html`, `esc_attr`, `wp_kses` as appropriate for admin and public views under `views/`, `public/`.
5. File includes: no user-controlled paths in `include`/`require` without validation.
6. `unserialize` / remote requests: grep and review hits.

-   [x] **Step 1:** Use `rg` (ripgrep) from repo root for patterns such as `wp_ajax_`, `register_rest_route`, `\$_REQUEST`, `eval(`, `unserialize(`.

```bash
rg 'wp_ajax_|register_rest_route|\$_REQUEST|unserialize\(' --glob '*.php' -n
```

-   [x] **Step 2:** For each hit category, sample top files from **Task 2** churn list first; record **Pass/Fail/NA** with path:line in `security-checklist-results.md`.

-   [x] **Step 3:** Summarize **Critical / High / Medium** in findings **Section C** with links to checklist lines.

-   [x] **Step 4:** Commit.

```bash
git add docs/superpowers/artifacts/security-checklist-results.md docs/superpowers/findings/2026-05-13-codebase-health-report.md
git commit -m "docs: add security checklist results for health audit"
```

---

### Task 7: Duplication signals (Wave B — duplication)

**Files:**

-   Modify: `docs/superpowers/artifacts/php-file-metrics.tsv` OR create `docs/superpowers/artifacts/duplication-notes.md`
-   Modify: `docs/superpowers/findings/2026-05-13-codebase-health-report.md` (**Section B**)

-   [x] **Step 1:** Run PHPCS baseline or duplicate-sniff if configured; if not, use `jscpd` / PMD-CPD only if already in repo—**do not** add heavy new tooling without team agreement. Minimum: manual `rg` for copy-paste anti-patterns (e.g. repeated `sanitize_text_field` blocks) in **top 20 churn** PHP files; document 3–5 concrete duplication clusters with paths.

-   [x] **Step 2:** Commit.

```bash
git add docs/superpowers/artifacts/duplication-notes.md docs/superpowers/findings/2026-05-13-codebase-health-report.md 2>/dev/null
git commit -m "docs: document duplication signals for health audit"
```

---

### Task 8: Executive summary and roadmap (close-out)

**Files:**

-   Modify: `docs/superpowers/findings/2026-05-13-codebase-health-report.md`

-   [x] **Step 1:** Fill **Executive summary** with quantified bullets (file counts, top subsystems, open security items count, top 3 test gaps).

-   [x] **Step 2:** Fill **Recommended roadmap** using design spec §6 ordering; each theme names a **single accountable owner** (person or role already used by the team, e.g. “EM”, “Plugin lead”).

-   [x] **Step 3:** Final read-through for contradictions vs `php-file-metrics.tsv` and matrices.

-   [x] **Step 4:** Commit.

```bash
git add docs/superpowers/findings/2026-05-13-codebase-health-report.md
git commit -m "docs: complete executive summary and roadmap for health report"
```

---

### Task 9: CI documentation (optional but recommended)

**Files:**

-   Create or modify: `.github/workflows/*.yml` **only if** team wants automation in this same effort; otherwise document in findings:

-   Add subsection **CI recommendations**: PR must run `composer test`; Playwright on `push` to `develop`/`main` + tags; document `npm run test:playwright` env (Node 20+, WP URL).

-   [x] **Step 1:** If workflow files exist, link them; if not, describe proposed jobs without merging until reviewed.

-   [x] **Step 2:** Commit doc-only or workflow change in isolated commit.

---

## Plan self-review (spec coverage)

| Design spec section | Tasks                                                     |
| ------------------- | --------------------------------------------------------- |
| §2 Success criteria | Tasks 3 (evidence), 2+7 (B), 4 (A), 6 (C), 5 (regression) |
| §3 Existing stack   | Task 3 references Composer + wp-scripts                   |
| §4 Deliverables     | Tasks 1–2, 5–6, 8                                         |
| §5 Methodology      | Tasks 2, 4, 6, 7                                          |
| §6 Roadmap themes   | Task 8                                                    |
| §7 Risks            | Task 4 (graphify caveat), Task 3 (env honesty)            |

**Placeholder scan:** No `TBD` / `TODO` in plan steps; owner names must be concrete in the shipped report.

---

## Execution handoff

**Plan complete and saved to** `docs/superpowers/plans/2026-05-13-codebase-health-audit-execution.md`.

**Two execution options:**

1. **Subagent-driven (recommended)** — Dispatch a fresh subagent per task; review between tasks.
2. **Inline execution** — Run tasks in one session with checkpoints after Tasks 3, 6, and 8.

**Which approach do you want?**
