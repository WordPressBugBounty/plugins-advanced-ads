# Advanced Ads — Tech Stack

**Plugin:** Advanced Ads v2.0.20  
**Requires:** WordPress ≥ 5.7, PHP ≥ 7.4  
**License:** GPL-2.0+  
**Knowledge graph:** Understand Anything (978 files, last analyzed 2026-06-15)

---

## Backend — PHP

| Concern            | Detail                                                                                                                  |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------- |
| Language           | PHP ≥ 7.4                                                                                                               |
| Autoloading        | PSR-4 (`Advanced_Ads\` → `src/`) + classmap for legacy (`includes/`, `admin/`, `classes/`, `public/`, selected modules) |
| Dependency manager | Composer                                                                                                                |
| Framework          | `advanced-ads/framework` (internal, dev-main)                                                                           |
| Mobile detection   | `mobiledetect/mobiledetectlib` 3.74.3                                                                                   |
| Background jobs    | `woocommerce/action-scheduler` ^3.9                                                                                     |

### PHP Dev Dependencies

| Package                                | Version | Purpose            |
| -------------------------------------- | ------- | ------------------ |
| `phpunit/phpunit`                      | ^9.6    | Unit testing       |
| `wp-coding-standards/wpcs`             | ^3.0.0  | Code style (PHPCS) |
| `phpcompatibility/phpcompatibility-wp` | ^2.1    | PHP compat checks  |
| `symfony/css-selector`                 | ^5.4    | Test utilities     |
| `yoast/phpunit-polyfills`              | ^4.0    | PHPUnit polyfills  |

### PHP Architecture Layers

```
src/                    PSR-4 namespaced (Advanced_Ads\) — new code
includes/               Classmap legacy — core plugin classes
admin/                  Classmap legacy — admin classes
classes/                Classmap legacy — shared helpers
public/                 Classmap legacy — frontend
modules/                Feature modules (ad-blocker, ad-positioning, adblock-finder,
                         ads-txt, gadsense, gutenberg, one-click, pef, privacy)
upgrades/               Versioned DB migration scripts
```

---

## Frontend — JavaScript / React

| Concern          | Detail                                                           |
| ---------------- | ---------------------------------------------------------------- |
| Runtime          | Node.js ≥ 20.0.0, npm ≥ 9.0.0                                    |
| UI framework     | React (via `@wordpress/scripts`)                                 |
| Language         | JavaScript + TypeScript                                          |
| Build tool       | Webpack (extended from `@wordpress/scripts` default config)      |
| CSS framework    | Tailwind CSS v4 (`@tailwindcss/postcss` ^4.1.14)                 |
| CSS processing   | PostCSS                                                          |
| State management | `@wordpress/data` (Redux-like store)                             |
| API layer        | `@wordpress/api-fetch` (REST)                                    |
| Icons            | `lucide-react` ^1.3.0                                            |
| Class utilities  | `clsx` ^2.1.1 + `tailwind-merge` ^3.5.0                          |
| Legacy widgets   | `select2` ^4.1.0-rc.0                                            |
| Routing          | Custom SPA router via `useSyncExternalStore` (URL search params) |
| SVG handling     | `@svgr/webpack` ^8.1.0                                           |

### Admin Screens (React SPAs)

| Screen     | Entry                          |
| ---------- | ------------------------------ |
| Ads        | `src/admin/screen-ads/`        |
| Ad Groups  | `src/admin/screen-groups/`     |
| Placements | `src/admin/screen-placements/` |
| Licenses   | `src/admin/screen-licenses/`   |
| Settings   | `src/admin/screen-settings/`   |
| Support    | `src/admin/screen-support/`    |
| Dashboard  | `src/admin/screen-dashboard/`  |

All screens share a client-side router (`src/admin/router.js`) and central route registry (`src/admin/routes.js`).

---

## Testing

| Layer    | Tool                    | Command                   |
| -------- | ----------------------- | ------------------------- |
| PHP unit | PHPUnit ^9.6            | `composer test`           |
| JS unit  | wp-scripts test-unit-js | `npm run test:unit`       |
| E2E      | Playwright              | `npm run test:playwright` |

---

## Code Quality

| Tool                   | Scope      | How invoked                         |
| ---------------------- | ---------- | ----------------------------------- |
| ESLint                 | JavaScript | `npm run lint:js`                   |
| Stylelint              | CSS/SCSS   | `npm run lint:css`                  |
| PHP_CodeSniffer (WPCS) | PHP        | `npm run lint:php` / `bin/phpcs.sh` |
| PHPMD                  | PHP        | `phpmd.xml` config present          |
| husky + lint-staged    | Pre-commit | Runs format + lint on staged files  |

---

## CI/CD — GitHub Actions

| Workflow                | Purpose                           |
| ----------------------- | --------------------------------- |
| `php-unit.yml`          | PHP unit test suite               |
| `php-compatibility.yml` | Cross-version PHP compat checks   |
| `playwright.yml`        | End-to-end browser tests          |
| `release.yml`           | Plugin release packaging          |
| `wordpress-deploy.yml`  | Deploy to WordPress.org / hosting |

---

## Tooling

| Tool                | Purpose                                                      |
| ------------------- | ------------------------------------------------------------ |
| Understand Anything | Codebase knowledge graph (`.understand-anything/`)           |
| WP-CLI              | DB/install helpers (`composer global require wp-cli/wp-cli`) |
| WP-CLI i18n         | Translation file generation                                  |
| wp-advads (custom)  | Internal build wrapper (`npx wp-advads build`)               |
