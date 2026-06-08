# Codebase health audit and regression confidence — design spec

**Date:** 2026-05-13  
**Status:** Approved for execution planning  
**Stakeholder goal:** High confidence that batches of changes do not regress product behavior.  
**Context:** Mature WordPress plugin codebase (~7 years); large corpus (see `graphify-out/GRAPH_REPORT.md` for scale and community structure).

---

## 1. Problem statement

Engineering leadership needs:

1. A **structured read** on technical debt, duplication, and risk (including security and complexity), without pretending every file received equal manual review.
2. A **prioritized improvement path** that favors **extensibility** and **safe change velocity**.
3. **Regression confidence** as the **primary** outcome: after _N_ changes, the team can justify that core behavior still works.

This spec defines **what** will be produced and **how** work will be sequenced. It does **not** prescribe specific code edits; those belong in an implementation plan written after this document is accepted.

---

## 2. Success criteria

| Criterion                | Measurable / verifiable                                                                                                                                                                        |
| ------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Evidence-backed findings | Claims tie to path + metric, checklist item, or test gap—not unsupported opinion                                                                                                               |
| Hybrid coverage          | Single master document includes heat-map style architecture (**A**), inventory and automation (**B**), and security/compliance (**C**) sections                                                |
| Regression strategy      | **PHPUnit** (`composer test` / `phpunit.xml.dist`) and **Playwright** via **`wp-scripts test-playwright`** are treated as the **two layers** of the safety net; gaps between them are explicit |
| Actionable backlog       | Themes (security, coupling, duplication, tests) with suggested order and definition of done per theme                                                                                          |
| Honest scope             | “Covered vs uncovered” is stated so stakeholders do not confuse partial automation with full product proof                                                                                     |

---

## 3. Existing test stack (baseline — do not replace)

The repository **already** provides:

-   **PHP unit tests:** `phpunit.xml.dist` bootstraps from `tests/bootstrap.php`; tests under `tests/Unit/`. Composer defines `composer test` → `phpunit` and `composer test-install` for WP test DB (`bin/install-wp-tests.sh`). Coverage includes `advanced-ads.php`, `admin/`, `classes/`, `includes/`, `modules/`, `public/`, `src/`, `views/`, `upgrades/`, `deprecated/`, etc.
-   **E2E (Playwright):** `playwright.config.ts` — `testDir` for acceptance under `tests/Acceptance/`, projects for **setup** (`auth.json`), **admin** / **admin-ads** / **admin-groups** / **admin-placements**, **frontend** / **frontend-auth**; optional overrides via `dev.config.json`.
-   **Runner integration:** `package.json` exposes `test:playwright`, `test:playwright:debug`, `test:playwright:headed` using **`wp-scripts test-playwright`**; JS unit tests use `wp-scripts test-unit-js` (`test:unit`).

**Design principle:** The audit and roadmap **extend and harden** this stack (coverage mapping, CI policy, flake control), not introduce parallel competing frameworks without cause.

---

## 4. Deliverables

### 4.1 Master findings document

One narrative document produced during the audit execution phase. **Recommended path:** `docs/superpowers/findings/<YYYY-MM-DD>-codebase-health-report.md` (or a dated successor), with a one-line pointer from team docs if needed. It contains:

-   **Executive summary** — goals, top risks, top recommendations (≤1 page equivalent).
-   **Section A — Architecture and heat map** — subsystems (bootstrap, admin, public output, placements/ads pipeline, integrations, upgrades, REST/AJAX, assets). Map high-churn and high-metric files into buckets; note coupling patterns (globals, shared entrypoints, cross-module includes).
-   **Section B — Inventory and automation** — methodology, metrics used, link or path to **machine-readable appendix** (CSV/JSON), and **manual deep dive** on worst _N_% (exact _N_ chosen in implementation plan; default range 5–10% of PHP sources or top _K_ files by composite score).
-   **Section C — Security and compliance** — checklist-driven findings (capabilities, nonces, sanitization/escaping, SQL, uploads, deserialization, cron, outbound HTTP, logging). Severity and exploitability sketch per finding; remediation pattern, not necessarily full patches in the findings doc.
-   **Regression confidence appendix** — mapping **critical product flows** → existing or missing **PHPUnit** cases and **Playwright** specs; CI trigger policy for **A** (every PR) vs **C** (nightly + release + optional PR label).

### 4.2 Machine-readable appendix

Generated or scripted artifact (format fixed in implementation plan; e.g. `docs/superpowers/artifacts/` or committed CSV under `docs/`) with per-file or per-module: size, age, churn, complexity proxy, duplication flags. Supports sorting and filtering without rereading prose.

### 4.3 Optional visual heat map

Table or diagram embedded in master doc: subsystem × risk signal × churn — derived from **B** and interpreted in **A**.

---

## 5. Methodology

### 5.1 Wave B — Inventory and signals (first)

-   Enumerate sources; compute **git churn**, size, and simple complexity proxies.
-   Run or integrate existing static tooling where already present (e.g. `npm run lint:php` / PHPCS); add others only if justified in implementation plan.
-   **Duplication:** prefer automated duplicate detection where feasible; otherwise pattern-based sampling with examples.
-   **Output:** ranked list; **manual** analysis concentrates on the **tail** (highest risk × highest churn).

### 5.2 Wave A — Architecture mapping

-   Assign files and clusters to **subsystem** buckets; align with graphify **hints** (`graphify-out/GRAPH_REPORT.md`) but **validate** against real boundaries (graph includes tooling/test communities—do not over-trust raw community IDs).
-   Identify **integration surfaces** and **change hotspots** for test investment.

### 5.3 Wave C — Security pass

-   Structured checklist on all **externally reachable** behavior: admin POST/AJAX, REST, cron, public query vars, file operations.
-   Correlate with **B** hotspots and recent incident patterns (e.g. past RCE class issues).

### 5.4 Regression program (A + C from stakeholder choice)

| Layer               | Technology                        | Intended CI role                                                                                                 |
| ------------------- | --------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| **Fast automated**  | PHPUnit + WP test bootstrap       | **Required** on every merge-worthy change (exact job matrix in implementation plan)                              |
| **Golden-path E2E** | Playwright + `@wordpress/scripts` | **Required** on schedule and release branches; optional on every PR via label or subset to manage duration/flake |

-   **Define critical flows** explicitly (product-specific; examples only: placement CRUD, ad output in theme, shortcode/block, upgrade path).
-   **Close gaps:** each flow has either a PHPUnit test, a Playwright spec, or a documented exception with compensating manual/release step.
-   **E2E stability:** fixture strategy (`tests/Acceptance/fixtures/`), auth setup project, `dev.config.json` conventions documented; flake budget and retry policy defined in implementation plan.

---

## 6. Roadmap themes (order of execution)

1. **Regression backbone** — Flow list; map to PHPUnit / Playwright; CI gates; reduce flake in existing Playwright projects (`admin`, `admin-ads`, `admin-groups`, `admin-placements`, `frontend`, `frontend-auth`, `setup`).
2. **Security hardening on surfaces** — **C** findings with quick wins merged behind tests where possible.
3. **Boundary stabilization** — Reduce coupling in hotspots identified in **A**; document module contracts.
4. **Dedup and extract** — Address duplication clusters from **B** that block extensibility.
5. **Continuous guardrails** — Maintain graphify after substantive edits (`graphify update .`); keep static analysis and test coverage trends visible.

Each theme exits with **definition of done** written in the implementation plan (e.g. “all REST routes audited,” “top 20 churn files have owner + test or E2E cover”).

---

## 7. Risks and mitigations

| Risk                               | Mitigation                                                |
| ---------------------------------- | --------------------------------------------------------- |
| Graphify noise (vendor/test nodes) | Use graph as **navigation**, confirm in source            |
| Static analysis false positives    | Human triage before “high” severity                       |
| E2E flakiness                      | Narrow scenarios, stable data, staged CI, documented env  |
| Stale analysis                     | Record git SHA; refresh artifacts after meaningful merges |

---

## 8. Out of scope (for this spec)

-   Rewriting production features not tied to findings.
-   Replacing `@wordpress/scripts` / Playwright / PHPUnit without explicit follow-on decision.
-   Legal/compliance certification (GDPR, SOC2) beyond security hygiene called out in **C**.

---

## 9. Approval and next steps

-   **Approved by stakeholder** for goals and structure: 2026-05-13.
-   **Next step:** Implementation plan (per project process: `writing-plans` skill) — concrete tasks, owners, tooling commands, artifact paths, and timeline for executing **B → A → C** documentation and **regression backlog** without blocking ongoing delivery.
