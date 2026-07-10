# Support Page Playwright Tests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 14 Playwright acceptance tests for the Support admin screen with mocked APIs and licensed/unlicensed matrix coverage.

**Architecture:** Shared `supportMocks.ts` + `supportPage.ts` helpers; one spec file per scenario under `tests/Acceptance/Admin/Support/`; new `admin-support` Playwright project reusing `auth.setup.ts`.

**Tech Stack:** Playwright Test, TypeScript, existing `adminPage.ts` utilities.

**Spec:** `docs/superpowers/specs/tests/support.plan.md`

---

### Task 1: Mocks and page helpers

**Files:**

-   Create: `tests/Acceptance/Admin/Support/supportMocks.ts`
-   Create: `tests/Acceptance/Admin/Support/supportPage.ts`
-   Modify: `playwright.config.ts`

-   [ ] Add `mockSupportLinks`, `mockSearchApi`, `withLicensedSite`, `withUnlicensedSite`
-   [ ] Add `gotoSupportPage`, `waitForCategoryCardsLoaded`, FAQ title constant
-   [ ] Register `admin-support` project in config

### Task 2: Smoke specs (1.1–1.5)

**Files:** `tests/Acceptance/Admin/Support/support-*.spec.ts` (5 files)

-   [ ] Implement page load, search hero, category cards, FAQs, videos tests

### Task 3: Interaction specs (2.1–2.4)

-   [ ] Search autocomplete, FAQ expand, UTM links, category new-tab tests

### Task 4: Unlicensed specs (3.1–3.2)

-   [ ] Upgrade card and forum link tests with `withUnlicensedSite`

### Task 5: Licensed specs (4.1–4.3)

-   [ ] Priority card, ticket modal, validation tests with `withLicensedSite`

### Task 6: Verify

-   [ ] Run `PLAYWRIGHT_HTML_OPEN=never npm run test:playwright -- --project=admin-support`
